<?php

/*
 * LectorXlsx — Lector de archivos Excel .xlsx en PHP puro.
 *
 * Un .xlsx es un contenedor ZIP con archivos XML. Esta clase descomprime el ZIP
 * manualmente (leyendo el directorio central y usando gzinflate de zlib) y parsea
 * el XML con SimpleXML, SIN depender de la extensión zip ni de librerías externas.
 *
 * Uso:
 *     require_once 'assets/librerias/lector_xlsx/lector_xlsx.php';
 *     $lector = new LectorXlsx($rutaArchivo);
 *     $filas  = $lector->leerFilas();   // [ ['A'=>'...', 'B'=>'...'], ... ]  (fila 1 = cabecera)
 *
 * Cada fila es un arreglo asociativo cuyas claves son la LETRA de la columna
 * (A, B, C, ...). Las celdas vacías se omiten, por eso se accede por letra y no
 * por posición.
 */
class LectorXlsx
{
    /** @var string Contenido binario completo del archivo .xlsx */
    private $binario;

    /** @var array Mapa: nombre interno => [metodo, tamComp, offsetLocal] */
    private $entradas = [];

    /**
     * @param string $ruta Ruta al archivo .xlsx.
     * @throws RuntimeException
     */
    public function __construct($ruta)
    {
        if (!is_readable($ruta)) {
            throw new RuntimeException('No se puede leer el archivo .xlsx.');
        }

        $binario = file_get_contents($ruta);
        if ($binario === false) {
            throw new RuntimeException('No se pudo abrir el archivo .xlsx.');
        }

        $this->binario = $binario;
        $this->leerDirectorioCentral();
    }

    /**
     * Recorre el "directorio central" del ZIP y registra la ubicación de cada
     * entrada (archivo interno) para poder extraerla luego.
     */
    private function leerDirectorioCentral()
    {
        // El registro "End Of Central Directory" (firma PK\05\06) está al final.
        $posEocd = strrpos($this->binario, "\x50\x4b\x05\x06");
        if ($posEocd === false) {
            throw new RuntimeException('El archivo no es un .xlsx válido (ZIP no reconocido).');
        }

        $eocd = unpack(
            'vdiscos/vdiscoCD/vregistrosDisco/vregistros/Vtam/Voffset/vlenComentario',
            substr($this->binario, $posEocd + 4, 18)
        );

        $puntero = $eocd['offset'];

        for ($i = 0; $i < $eocd['registros']; $i++) {

            // Cada entrada del directorio central empieza con la firma PK\01\02.
            if (substr($this->binario, $puntero, 4) !== "\x50\x4b\x01\x02") {
                break;
            }

            $cab = unpack(
                'vversionHecha/vversionNec/vflags/vmetodo/vhora/vfecha/Vcrc/'
                . 'VtamComp/VtamDescomp/vlenNombre/vlenExtra/vlenComentario/'
                . 'vdiscoInicio/vatribInt/VatribExt/VoffsetLocal',
                substr($this->binario, $puntero + 4, 42)
            );

            $nombre = substr($this->binario, $puntero + 46, $cab['lenNombre']);

            $this->entradas[$nombre] = [
                'metodo'      => $cab['metodo'],      // 0 = sin comprimir, 8 = deflate
                'tamComp'     => $cab['tamComp'],     // tamaño comprimido (fiable aquí)
                'offsetLocal' => $cab['offsetLocal'], // dónde está su cabecera local
            ];

            $puntero += 46 + $cab['lenNombre'] + $cab['lenExtra'] + $cab['lenComentario'];
        }
    }

    /**
     * Devuelve el contenido descomprimido de una entrada interna del .xlsx.
     *
     * @param string $nombre Ruta interna (ej: 'xl/worksheets/sheet1.xml').
     * @return string|false  El contenido, o false si la entrada no existe.
     */
    public function obtenerContenido($nombre)
    {
        if (!isset($this->entradas[$nombre])) {
            return false;
        }

        $entrada = $this->entradas[$nombre];
        $offset  = $entrada['offsetLocal'];

        if (substr($this->binario, $offset, 4) !== "\x50\x4b\x03\x04") {
            throw new RuntimeException('Cabecera local del ZIP inválida.');
        }

        // La cabecera local indica el largo de nombre/extra para ubicar los datos.
        $local = unpack(
            'vversionNec/vflags/vmetodo/vhora/vfecha/Vcrc/VtamComp/VtamDescomp/vlenNombre/vlenExtra',
            substr($this->binario, $offset + 4, 26)
        );

        $inicioDatos = $offset + 30 + $local['lenNombre'] + $local['lenExtra'];
        $comprimido  = substr($this->binario, $inicioDatos, $entrada['tamComp']);

        // 0 = almacenado tal cual; 8 = deflate (se descomprime con gzinflate).
        if ($entrada['metodo'] === 0) {
            return $comprimido;
        }

        if ($entrada['metodo'] === 8) {
            $contenido = gzinflate($comprimido);
            if ($contenido === false) {
                throw new RuntimeException('No se pudo descomprimir el contenido del .xlsx.');
            }
            return $contenido;
        }

        throw new RuntimeException('Método de compresión del ZIP no soportado.');
    }

    /**
     * Lee las filas de una hoja y las devuelve como arreglos por letra de columna.
     *
     * @param int $numeroHoja Número de hoja (1 = primera). Asume el nombre estándar
     *                        xl/worksheets/sheet{N}.xml.
     * @return array Lista de filas; cada fila es ['A'=>valor, 'B'=>valor, ...].
     * @throws RuntimeException
     */
    public function leerFilas($numeroHoja = 1)
    {
        $hojaXml = $this->obtenerContenido('xl/worksheets/sheet' . $numeroHoja . '.xml');
        if ($hojaXml === false) {
            throw new RuntimeException('No se encontró la hoja solicitada en el .xlsx.');
        }

        return $this->parsearFilas($hojaXml);
    }

    /**
     * Lee las filas de una hoja identificada por su NOMBRE de pestaña (ej: 'base'),
     * resolviendo xl/workbook.xml + sus relaciones. Más robusto que asumir el número
     * de archivo sheet{N}.xml, porque el orden de las pestañas no siempre coincide
     * con el número de archivo interno.
     *
     * @param string $nombreHoja Nombre de la pestaña (no distingue mayúsculas/minúsculas).
     * @return array Lista de filas; cada fila es ['A'=>valor, ...].
     * @throws RuntimeException si no existe una hoja con ese nombre.
     */
    public function leerFilasPorNombre($nombreHoja)
    {
        $archivo = $this->resolverArchivoHoja($nombreHoja);
        if ($archivo === null) {
            throw new RuntimeException('No se encontró la hoja "' . $nombreHoja . '" en el archivo.');
        }

        $hojaXml = $this->obtenerContenido($archivo);
        if ($hojaXml === false) {
            throw new RuntimeException('No se pudo leer la hoja "' . $nombreHoja . '".');
        }

        return $this->parsearFilas($hojaXml);
    }

    /**
     * Resuelve la ruta interna del XML de una hoja a partir de su nombre, usando
     * xl/workbook.xml (nombre -> r:id) y xl/_rels/workbook.xml.rels (r:id -> archivo).
     *
     * @return string|null Ruta interna (ej: 'xl/worksheets/sheet8.xml') o null si no existe.
     */
    private function resolverArchivoHoja($nombreHoja)
    {
        $wbXml = $this->obtenerContenido('xl/workbook.xml');
        if ($wbXml === false) {
            return null;
        }

        // Mapa nombre -> r:id. El atributo r:id vive en el namespace de relaciones
        // (se conserva tras quitarNamespace, que solo elimina el namespace por defecto).
        $wb  = new SimpleXMLElement($this->quitarNamespace($wbXml));
        $rid = null;

        foreach ($wb->sheets->sheet as $hoja) {
            if (strcasecmp(trim((string) $hoja['name']), trim($nombreHoja)) === 0) {
                $attrs = $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid   = isset($attrs['id']) ? (string) $attrs['id'] : '';
                break;
            }
        }

        if ($rid === null || $rid === '') {
            return null;
        }

        // Mapa r:id -> Target (archivo de la hoja, relativo a xl/).
        $relsXml = $this->obtenerContenido('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            return null;
        }

        $rels = new SimpleXMLElement($this->quitarNamespace($relsXml));
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                $target = preg_replace('#^xl/#', '', $target);
                return 'xl/' . $target;
            }
        }

        return null;
    }

    /**
     * Parsea el XML de una hoja a una lista de filas (cada una ['A'=>valor, ...]).
     */
    private function parsearFilas($hojaXml)
    {
        $textos = $this->leerTextosCompartidos();

        $hoja  = new SimpleXMLElement($this->quitarNamespace($hojaXml));
        $filas = [];

        foreach ($hoja->sheetData->row as $fila) {

            $celdas = [];

            foreach ($fila->c as $celda) {

                $ref     = (string) $celda['r'];   // ej: "B2"
                $tipo    = (string) $celda['t'];   // 's' (texto), 'inlineStr', o vacío (número)
                $columna = preg_replace('/[0-9]+/', '', $ref);

                if ($tipo === 's') {
                    $indice = (int) $celda->v;
                    $valor  = isset($textos[$indice]) ? $textos[$indice] : '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string) $celda->is->t;
                } else {
                    $valor = (string) $celda->v;
                }

                $celdas[$columna] = $valor;
            }

            $filas[] = $celdas;
        }

        return $filas;
    }

    /**
     * Carga la tabla de textos compartidos (sharedStrings.xml). En un .xlsx los
     * textos se guardan una sola vez aquí y las celdas los referencian por índice.
     *
     * @return array Lista de strings indexada por posición.
     */
    private function leerTextosCompartidos()
    {
        $textos = [];

        $xml = $this->obtenerContenido('xl/sharedStrings.xml');
        if ($xml === false) {
            return $textos;
        }

        $sst = new SimpleXMLElement($this->quitarNamespace($xml));

        foreach ($sst->si as $si) {

            $texto = '';

            // Texto simple: <si><t>...</t></si>
            foreach ($si->t as $t) {
                $texto .= (string) $t;
            }

            // Texto enriquecido: <si><r><t>...</t></r>...</si>
            foreach ($si->r as $r) {
                foreach ($r->t as $t) {
                    $texto .= (string) $t;
                }
            }

            $textos[] = $texto;
        }

        return $textos;
    }

    /**
     * Elimina la declaración de namespace por defecto del XML para poder
     * navegarlo con SimpleXML sin prefijos. Los .xlsx declaran un namespace
     * por defecto que dificulta el acceso directo a elementos y atributos.
     */
    private function quitarNamespace($xml)
    {
        return preg_replace('/\sxmlns="[^"]*"/', '', $xml, 1);
    }
}
