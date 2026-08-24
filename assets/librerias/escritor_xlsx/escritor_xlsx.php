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
 * Detección de tipo por celda: los valores numéricos se escriben como NÚMERO (para que Excel
 * los sume/ordene); el resto como texto. Los "números" con cero a la izquierda (ej. "007") se
 * dejan como texto para no perder el formato del código.
 */
class EscritorXlsx
{
    /** @var string Nombre de la hoja (saneado, máx 31 caracteres). */
    private $nombreHoja;

    /** @var array|null Fila de encabezado (se pinta en negrita). */
    private $encabezado = null;

    /** @var array Filas de datos (cada una es un arreglo de valores). */
    private $filas = [];

    public function __construct($nombreHoja = 'Hoja1')
    {
        $this->nombreHoja = $this->sanitizarHoja($nombreHoja);
    }

    /** Define la fila de encabezado (negrita). Llamar una vez, antes o después de las filas. */
    public function encabezados(array $titulos)
    {
        $this->encabezado = array_values($titulos);
        return $this;
    }

    /** Agrega una fila de datos. */
    public function fila(array $valores)
    {
        $this->filas[] = array_values($valores);
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
        $sheet = $this->construirSheet();

        $archivos = [
            '[Content_Types].xml'         => $this->xmlContentTypes(),
            '_rels/.rels'                 => $this->xmlRelsRaiz(),
            'xl/workbook.xml'             => $this->xmlWorkbook(),
            'xl/_rels/workbook.xml.rels'  => $this->xmlWorkbookRels(),
            'xl/styles.xml'               => $this->xmlStyles(),
            'xl/worksheets/sheet1.xml'    => $sheet,
        ];

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

    private function construirSheet()
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        $numeroFila = 1;

        if ($this->encabezado !== null) {
            $xml .= $this->construirFila($numeroFila, $this->encabezado, true);
            $numeroFila++;
        }

        foreach ($this->filas as $fila) {
            $xml .= $this->construirFila($numeroFila, $fila, false);
            $numeroFila++;
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    /** XML de una fila (<row>) con sus celdas. */
    private function construirFila($numeroFila, array $valores, $esEncabezado)
    {
        $celdas = '';
        $columna = 1;

        foreach ($valores as $valor) {
            $ref     = $this->letraColumna($columna) . $numeroFila;
            $celdas .= $this->construirCelda($ref, $valor, $esEncabezado);
            $columna++;
        }

        return '<row r="' . $numeroFila . '">' . $celdas . '</row>';
    }

    /** XML de una celda (<c>): número si aplica, si no texto (inline string). */
    private function construirCelda($ref, $valor, $esEncabezado)
    {
        $estilo = $esEncabezado ? ' s="1"' : '';

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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->escaparXml($this->nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function xmlWorkbookRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** Estilos mínimos: fuente normal (0) y negrita (1) para el encabezado (s="1"). */
    private function xmlStyles()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2">'
            .   '<fill><patternFill patternType="none"/></fill>'
            .   '<fill><patternFill patternType="gray125"/></fill>'
            . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
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
}
