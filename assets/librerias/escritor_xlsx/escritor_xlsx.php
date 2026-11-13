<?php

/*
 * EscritorXlsx — Generador de archivos Excel .xlsx en PHP puro (sin librerías externas).
 *
 * Es el complemento de LectorXlsx: un .xlsx es un contenedor ZIP con varios archivos XML.
 * Esta clase genera esos XML (con inline strings) y arma el ZIP MANUALMENTE (cabeceras +
 * crc32 + pack, método "stored"/sin comprimir), sin depender de la extensión zip (ZipArchive)
 * ni de Composer. Produce un .xlsx nativo que Excel abre sin advertencias.
 *
 * Uso:
 *     require_once 'assets/librerias/escritor_xlsx/escritor_xlsx.php';
 *     $xlsx = new EscritorXlsx('Facturas');
 *     $xlsx->encabezados(['Código', 'Artículo', 'Cantidad', 'Total']);
 *     foreach ($filas as $f) {
 *         $xlsx->fila([$f['cod'], $f['nombre'], $f['cant'], $f['total']]);
 *     }
 *     $xlsx->descargar('consulta.xlsx');   // envía el archivo al navegador
 *     exit;
 *
 * Varias hojas (opcional): la primera la crea el constructor; hoja('Nombre') inicia otra y la
 * vuelve la hoja activa (las siguientes llamadas a encabezados()/fila() van ahí):
 *     $xlsx = new EscritorXlsx('Comprados');
 *     $xlsx->encabezados([...]);  $xlsx->fila([...]);
 *     $xlsx->hoja('Sin Comprar'); $xlsx->encabezados([...]);  $xlsx->fila([...]);
 *     $xlsx->descargar('plan.xlsx');
 *
 * Detección de tipo por celda: los valores numéricos se escriben como NÚMERO (para que Excel
 * los sume/ordene); el resto como texto. Los "números" con cero a la izquierda (ej. "007") se
 * dejan como texto para no perder el formato del código.
 *
 * Fechas (opcional, ver fechasComoFecha()): los textos 'yyyy-mm-dd' (con o sin hora, como
 * vienen de SQL Server) se escriben como FECHA real de Excel con formato dd-mm-aaaa, para que
 * se puedan ordenar y filtrar como fecha. Va desactivado por defecto: las plantillas que se
 * vuelven a cargar (ej. Forecast Personalizado) esperan el texto 'yyyy-mm-dd'.
 */
class EscritorXlsx
{
    /** @var array Hojas del libro: cada una ['nombre'=>, 'encabezado'=>array|null, 'filas'=>array]. */
    private $hojas = [];

    /** @var int Índice (0-based) de la hoja activa: a ella van encabezados()/fila()/filas(). */
    private $idxActual = 0;

    /** @var bool Escribir los textos 'yyyy-mm-dd[ hh:mm[:ss]]' como fecha de Excel (todas las hojas). */
    private $fechasComoFecha = false;

    /** @var array Columnas con fecha (índice 1-based => true si alguna lleva hora), para su ancho. */
    private $colsFecha = [];

    public function __construct($nombreHoja = 'Hoja1')
    {
        $this->nuevaHojaInterna($nombreHoja);
    }

    /** Agrega una hoja al libro y la deja como activa. */
    private function nuevaHojaInterna($nombre)
    {
        $this->hojas[]   = ['nombre' => $this->sanitizarHoja($nombre), 'encabezado' => null, 'filas' => []];
        $this->idxActual = count($this->hojas) - 1;
    }

    /** Inicia una NUEVA hoja y la vuelve la hoja activa (las siguientes filas van ahí). */
    public function hoja($nombre)
    {
        $this->nuevaHojaInterna($nombre);
        return $this;
    }

    /** Activa la escritura de fechas 'yyyy-mm-dd' como fecha real de Excel (dd-mm-aaaa). */
    public function fechasComoFecha($activar = true)
    {
        $this->fechasComoFecha = (bool) $activar;
        return $this;
    }

    /** Define la fila de encabezado (negrita) de la hoja activa. Antes o después de sus filas. */
    public function encabezados(array $titulos)
    {
        $this->hojas[$this->idxActual]['encabezado'] = array_values($titulos);
        return $this;
    }

    /** Agrega una fila de datos a la hoja activa. */
    public function fila(array $valores)
    {
        $this->hojas[$this->idxActual]['filas'][] = array_values($valores);
        return $this;
    }

    /** Agrega varias filas de datos de una vez. */
    public function filas(array $filas)
    {
        foreach ($filas as $f) {
            $this->fila((array) $f);
        }
        return $this;
    }

    /**
     * Devuelve el binario del archivo .xlsx.
     * @return string
     */
    public function generar()
    {
        $archivos = [
            '[Content_Types].xml'         => $this->xmlContentTypes(),
            '_rels/.rels'                 => $this->xmlRelsRaiz(),
            'xl/workbook.xml'             => $this->xmlWorkbook(),
            'xl/_rels/workbook.xml.rels'  => $this->xmlWorkbookRels(),
            'xl/styles.xml'               => $this->xmlStyles(),
        ];
        foreach ($this->hojas as $i => $hoja) {
            $archivos['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->construirSheet($hoja);
        }

        return $this->construirZip($archivos);
    }

    /**
     * Envía el .xlsx al navegador como descarga. NO llama a exit(): debe ser la única salida
     * del script (el controlador hace exit; después).
     *
     * @param string $nombreArchivo Nombre sugerido (se le agrega .xlsx si falta).
     */
    public function descargar($nombreArchivo)
    {
        $bin = $this->generar();

        if (!preg_match('/\.xlsx$/i', $nombreArchivo)) {
            $nombreArchivo .= '.xlsx';
        }

        // Content-Disposition con nombre en UTF-8: filename ASCII de respaldo + filename* (RFC 5987)
        // para que los acentos (Órdenes, Crédito…) se conserven en navegadores modernos.
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $nombreArchivo);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombreArchivo));
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: public');

        echo $bin;
    }

    // ==========================================================================
    //  Construcción de la hoja (sheet1.xml)
    // ==========================================================================

    private function construirSheet(array $hoja)
    {
        $this->colsFecha = [];
        $filasXml   = '';
        $numeroFila = 1;

        if ($hoja['encabezado'] !== null) {
            $filasXml .= $this->construirFila($numeroFila, $hoja['encabezado'], true);
            $numeroFila++;
        }

        foreach ($hoja['filas'] as $fila) {
            $filasXml .= $this->construirFila($numeroFila, $fila, false);
            $numeroFila++;
        }

        // Ancho de las columnas con fecha: Excel muestra "####" si una fecha no cabe (a
        // diferencia del texto, que se desborda a la celda vecina).
        $cols = '';
        if ($this->colsFecha) {
            ksort($this->colsFecha);
            foreach ($this->colsFecha as $col => $conHora) {
                $ancho = $conHora ? 17 : 12;
                $cols .= '<col min="' . $col . '" max="' . $col . '" width="' . $ancho . '" customWidth="1"/>';
            }
            $cols = '<cols>' . $cols . '</cols>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols . '<sheetData>' . $filasXml . '</sheetData></worksheet>';
    }

    /** XML de una fila (<row>) con sus celdas. */
    private function construirFila($numeroFila, array $valores, $esEncabezado)
    {
        $celdas = '';
        $columna = 1;

        foreach ($valores as $valor) {
            $ref     = $this->letraColumna($columna) . $numeroFila;
            $celdas .= $this->construirCelda($ref, $valor, $esEncabezado, $columna);
            $columna++;
        }

        return '<row r="' . $numeroFila . '">' . $celdas . '</row>';
    }

    /**
     * XML de una celda (<c>): número si aplica, si no texto (inline string).
     * El valor puede ser escalar, o un arreglo ['valor'=>x, 'relleno'=>'verde'|'amarillo'|'rojo']
     * para pintar el fondo de la celda (ver índices en xmlStyles()).
     * Con fechasComoFecha(), un texto 'yyyy-mm-dd[ hh:mm[:ss]]' sin relleno se escribe como
     * fecha de Excel (número de serie + formato dd-mm-aaaa).
     */
    private function construirCelda($ref, $valor, $esEncabezado, $columna = 0)
    {
        $idxEstilo = $esEncabezado ? 1 : 0;

        if (is_array($valor)) {
            $relleno = $valor['relleno'] ?? null;
            $valor   = $valor['valor'] ?? '';
            $mapa    = ['verde' => 2, 'amarillo' => 3, 'rojo' => 4, 'gris' => 5];
            if ($relleno !== null && isset($mapa[$relleno])) { $idxEstilo = $mapa[$relleno]; }
        }

        if ($this->fechasComoFecha && $idxEstilo === 0) {
            $fecha = $this->serialFecha($valor);
            if ($fecha !== null) {
                [$serial, $conHora] = $fecha;
                $this->colsFecha[$columna] = ($this->colsFecha[$columna] ?? false) || $conHora;
                return '<c r="' . $ref . '" s="' . ($conHora ? 7 : 6) . '"><v>' . $serial . '</v></c>';
            }
        }

        $estilo = $idxEstilo ? ' s="' . $idxEstilo . '"' : '';

        if ($this->esNumero($valor)) {
            return '<c r="' . $ref . '"' . $estilo . '><v>' . $valor . '</v></c>';
        }

        $texto = $this->escaparXml($valor);
        return '<c r="' . $ref . '"' . $estilo . ' t="inlineStr"><is><t xml:space="preserve">' . $texto . '</t></is></c>';
    }

    // ==========================================================================
    //  Partes fijas del paquete .xlsx
    // ==========================================================================

    private function xmlContentTypes()
    {
        $hojasOverride = '';
        foreach ($this->hojas as $i => $hoja) {
            $hojasOverride .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" '
                . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $hojasOverride
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function xmlRelsRaiz()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function xmlWorkbook()
    {
        $sheets = '';
        $usados = [];
        foreach ($this->hojas as $i => $hoja) {
            $nombre  = $this->nombreUnico($hoja['nombre'], $usados);
            $sheets .= '<sheet name="' . $this->escaparXml($nombre) . '" sheetId="' . ($i + 1)
                . '" r:id="rId' . ($i + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function xmlWorkbookRels()
    {
        $rels = '';
        foreach ($this->hojas as $i => $hoja) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" '
                . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
                . 'Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        // La hoja de estilos va después de las hojas (rId siguiente).
        $rels .= '<Relationship Id="rId' . (count($this->hojas) + 1) . '" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" '
            . 'Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    /**
     * Estilos. Índices usados en las celdas (s="N"):
     *   0 = normal · 1 = negrita (encabezado)
     *   2 = relleno verde + texto blanco   (estado "Ok" / calidad "Alta")
     *   3 = relleno amarillo + texto negro (estado "Ajustado" / calidad "Media")
     *   4 = relleno rojo + texto blanco    (estado "Quiebre" / calidad "Baja")
     *   5 = relleno gris + texto blanco    (calidad "Manual" / secundario)
     *   6 = fecha dd-mm-aaaa · 7 = fecha con hora dd-mm-aaaa hh:mm (ver fechasComoFecha())
     * Colores en ARGB (FF + RRGGBB), iguales a los badges del MRP/Forecast:
     *   verde #198754 · amarillo #ffc107 · rojo #dc3545 · gris #6c757d.
     */
    private function xmlStyles()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2">'
            .   '<numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/>'
            .   '<numFmt numFmtId="165" formatCode="dd\-mm\-yyyy\ hh:mm"/>'
            . '</numFmts>'
            . '<fonts count="3">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="6">'
            .   '<fill><patternFill patternType="none"/></fill>'
            .   '<fill><patternFill patternType="gray125"/></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF198754"/></patternFill></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFFFC107"/></patternFill></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFDC3545"/></patternFill></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF6C757D"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .   '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .   '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/>'
            .   '<xf numFmtId="0" fontId="2" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .   '<xf numFmtId="0" fontId="2" fillId="5" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    // ==========================================================================
    //  Empaquetado ZIP manual (método "stored", sin comprimir)
    // ==========================================================================

    /**
     * Arma un ZIP con las entradas dadas (nombre => contenido), sin compresión.
     * @param array $archivos  Mapa nombreInterno => contenido.
     * @return string Binario del ZIP.
     */
    private function construirZip(array $archivos)
    {
        $datosLocales = '';   // headers locales + contenido de cada archivo
        $directorio   = '';   // registros del directorio central
        $offset       = 0;    // offset del header local de cada archivo
        $cantidad     = 0;

        foreach ($archivos as $nombre => $contenido) {
            $crc = crc32($contenido);
            $tam = strlen($contenido);

            // --- Local file header (0x04034b50) ---
            $local  = pack('V', 0x04034b50)
                    . pack('v', 20)      // versión requerida
                    . pack('v', 0)       // flags
                    . pack('v', 0)       // método 0 = stored
                    . pack('v', 0)       // hora
                    . pack('v', 0x21)    // fecha (1980-01-01)
                    . pack('V', $crc)
                    . pack('V', $tam)    // tamaño comprimido = sin comprimir
                    . pack('V', $tam)    // tamaño sin comprimir
                    . pack('v', strlen($nombre))
                    . pack('v', 0)       // largo extra
                    . $nombre;

            $datosLocales .= $local . $contenido;

            // --- Central directory record (0x02014b50) ---
            $directorio .= pack('V', 0x02014b50)
                        . pack('v', 20)   // versión creador
                        . pack('v', 20)   // versión requerida
                        . pack('v', 0)    // flags
                        . pack('v', 0)    // método
                        . pack('v', 0)    // hora
                        . pack('v', 0x21) // fecha
                        . pack('V', $crc)
                        . pack('V', $tam)
                        . pack('V', $tam)
                        . pack('v', strlen($nombre))
                        . pack('v', 0)    // extra
                        . pack('v', 0)    // comentario
                        . pack('v', 0)    // disco inicial
                        . pack('v', 0)    // atributos internos
                        . pack('V', 0)    // atributos externos
                        . pack('V', $offset)
                        . $nombre;

            $offset += strlen($local) + $tam;
            $cantidad++;
        }

        // --- End of central directory (0x06054b50) ---
        $eocd = pack('V', 0x06054b50)
              . pack('v', 0)             // número de disco
              . pack('v', 0)             // disco con el directorio central
              . pack('v', $cantidad)     // entradas en este disco
              . pack('v', $cantidad)     // entradas totales
              . pack('V', strlen($directorio))
              . pack('V', strlen($datosLocales))
              . pack('v', 0);            // largo del comentario

        return $datosLocales . $directorio . $eocd;
    }

    // ==========================================================================
    //  Utilidades
    // ==========================================================================

    /** Convierte un índice de columna (1..N) a su letra Excel (1->A, 27->AA). */
    private function letraColumna($n)
    {
        $letra = '';
        while ($n > 0) {
            $resto = ($n - 1) % 26;
            $letra = chr(65 + $resto) . $letra;
            $n     = intdiv($n - 1, 26);
        }
        return $letra;
    }

    /** ¿El valor debe escribirse como número? (evita perder ceros a la izquierda). */
    private function esNumero($valor)
    {
        if (is_int($valor) || is_float($valor)) {
            return true;
        }
        if (!is_string($valor) || $valor === '') {
            return false;
        }
        if (!is_numeric($valor)) {
            return false;
        }
        // "007" -> texto (no perder el cero). "0" y "0.5" -> número.
        if (strlen($valor) > 1 && $valor[0] === '0' && $valor[1] !== '.') {
            return false;
        }
        return true;
    }

    /**
     * Si $valor es un texto de fecha 'yyyy-mm-dd' (opcionalmente con ' hh:mm[:ss[.fff]]' o 'T'),
     * devuelve [número de serie de Excel, ¿lleva hora distinta de 00:00?]; si no, null.
     * Serie de Excel = días desde 1899-12-30 (fecha Unix 0 = 25569), más la fracción del día.
     */
    private function serialFecha($valor)
    {
        if (!is_string($valor)
            || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?)?$/', $valor, $m)) {
            return null;
        }
        [$a, $mes, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if (!checkdate($mes, $d, $a) || $a < 1900) {
            return null;
        }
        $h = (int) ($m[4] ?? 0); $min = (int) ($m[5] ?? 0); $s = (int) ($m[6] ?? 0);
        $serial = intdiv(gmmktime(0, 0, 0, $mes, $d, $a), 86400) + 25569;
        $conHora = ($h || $min || $s);
        if ($conHora) {
            $serial += ($h * 3600 + $min * 60 + $s) / 86400;
        }
        return [$serial, (bool) $conHora];
    }

    /** Escapa texto para XML y quita caracteres de control no válidos en XML 1.0. */
    private function escaparXml($valor)
    {
        $s = (string) $valor;
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Sanea el nombre de la hoja: sin caracteres prohibidos y máx 31 caracteres. */
    private function sanitizarHoja($nombre)
    {
        $n = preg_replace('/[:\\\\\/?*\[\]]/', ' ', (string) $nombre);
        $n = trim($n);
        if ($n === '') {
            $n = 'Hoja1';
        }
        return mb_substr($n, 0, 31);
    }

    /**
     * Garantiza un nombre de hoja único en el libro (Excel no admite dos hojas con el mismo
     * nombre, sin distinguir mayúsculas). Si se repite, agrega un sufijo " (2)", " (3)"…
     * respetando el máximo de 31 caracteres. Registra el nombre usado en $usados (por referencia).
     */
    private function nombreUnico($nombre, array &$usados)
    {
        $base   = ($nombre === '') ? 'Hoja' : $nombre;
        $nombre = $base;
        $n      = 2;
        while (isset($usados[mb_strtolower($nombre)])) {
            $sufijo = ' (' . $n . ')';
            $nombre = mb_substr($base, 0, 31 - mb_strlen($sufijo)) . $sufijo;
            $n++;
        }
        $usados[mb_strtolower($nombre)] = true;
        return $nombre;
    }
}
