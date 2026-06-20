<?php

/*
 * ProcesadorSvg — sanea y normaliza archivos SVG en PHP puro (DOMDocument),
 * sin librerías externas.
 *
 *  - sanear():            quita elementos/atributos peligrosos (script, eventos on*,
 *                         foreignObject, image, iframe, href javascript:).
 *  - esMonocromatico():   true si el icono usa a lo sumo 1 color (sin degradados).
 *  - aplicarCurrentColor(): en los monocromáticos cambia los colores a `currentColor`
 *                         para que se puedan teñir por CSS.
 *  - viewBox():           devuelve el viewBox (lo deriva de width/height si falta).
 *  - svgComoString():     el SVG procesado completo (para guardarlo como archivo).
 *  - comoSimbolo($id):    un <symbol id viewBox> listo para el sprite combinado.
 */
class ProcesadorSvg
{
    /** @var DOMDocument */
    private $dom;

    /** @var DOMElement */
    private $svg;

    /** Elementos que se eliminan al sanear (comparados en minúscula por localName). */
    const ELEMENTOS_PROHIBIDOS = ['script', 'foreignobject', 'image', 'iframe', 'audio', 'video', 'use', 'a'];

    public function __construct($contenido)
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', (string) $contenido);   // quita BOM
        $contenido = trim($contenido);

        if ($contenido === '') {
            throw new RuntimeException('El archivo SVG está vacío.');
        }

        $this->dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $this->dom->loadXML($contenido, LIBXML_NONET);   // LIBXML_NONET: sin acceso a red
        libxml_clear_errors();

        if (!$ok || !$this->dom->documentElement || strtolower($this->dom->documentElement->localName) !== 'svg') {
            throw new RuntimeException('El archivo no es un SVG válido.');
        }

        $this->svg = $this->dom->documentElement;
    }

    /** Quita elementos y atributos peligrosos. Devuelve $this para encadenar. */
    public function sanear()
    {
        foreach ($this->todosLosElementos() as $el) {

            // Elemento prohibido completo.
            if (in_array(strtolower($el->localName), self::ELEMENTOS_PROHIBIDOS, true)) {
                if ($el->parentNode) {
                    $el->parentNode->removeChild($el);
                }
                continue;
            }

            // Atributos peligrosos: eventos on* y href con javascript:.
            if ($el->attributes && $el->attributes->length) {
                $nombres = [];
                foreach ($el->attributes as $a) {
                    $nombres[] = $a->nodeName;
                }
                foreach ($nombres as $nombre) {
                    $bajo = strtolower($nombre);
                    if (strpos($bajo, 'on') === 0) {
                        $el->removeAttribute($nombre);
                    } elseif (in_array($bajo, ['href', 'xlink:href'], true)
                              && stripos(trim($el->getAttribute($nombre)), 'javascript:') === 0) {
                        $el->removeAttribute($nombre);
                    }
                }
            }
        }

        return $this;
    }

    /** True si el SVG es monocromático (<= 1 color y sin degradados/patrones/url). */
    public function esMonocromatico()
    {
        $colores    = [];
        $multicolor = false;

        foreach ($this->todosLosElementos() as $el) {
            $tag = strtolower($el->localName);

            if (in_array($tag, ['lineargradient', 'radialgradient', 'pattern'], true)) {
                $multicolor = true;
            }

            if ($tag === 'stop') {
                $sc = $this->colorDe($el, 'stop-color');
                if ($sc !== null) {
                    $colores[strtolower($sc)] = true;
                }
            }

            foreach (['fill', 'stroke'] as $prop) {
                $c = $this->colorDe($el, $prop);
                if ($c === null) {
                    continue;
                }
                if (stripos($c, 'url(') === 0) {
                    $multicolor = true;   // referencia a degradado/patrón
                } else {
                    $colores[strtolower($c)] = true;
                }
            }
        }

        return !$multicolor && count($colores) <= 1;
    }

    /** En los monocromáticos, reemplaza los colores por currentColor. */
    public function aplicarCurrentColor()
    {
        // El svg raíz hereda currentColor (cubre los rellenos negros por defecto).
        $this->svg->setAttribute('fill', 'currentColor');

        foreach ($this->todosLosElementos() as $el) {

            foreach (['fill', 'stroke'] as $prop) {
                if ($el->hasAttribute($prop) && $this->esColorReal($el->getAttribute($prop))) {
                    $el->setAttribute($prop, 'currentColor');
                }
            }

            if ($el->hasAttribute('style')) {
                $estilo = preg_replace_callback(
                    '/(fill|stroke)\s*:\s*([^;]+)/i',
                    function ($m) {
                        return $this->esColorReal($m[2]) ? $m[1] . ':currentColor' : $m[0];
                    },
                    $el->getAttribute('style')
                );
                $el->setAttribute('style', $estilo);
            }
        }

        return $this;
    }

    /** Devuelve el viewBox; si falta, lo deriva de width/height; por defecto 0 0 16 16. */
    public function viewBox()
    {
        $vb = trim($this->svg->getAttribute('viewBox'));
        if ($vb !== '') {
            return $vb;
        }

        $w = $this->soloNumero($this->svg->getAttribute('width'));
        $h = $this->soloNumero($this->svg->getAttribute('height'));
        if ($w > 0 && $h > 0) {
            return '0 0 ' . rtrim(rtrim(number_format($w, 4, '.', ''), '0'), '.')
                 . ' '    . rtrim(rtrim(number_format($h, 4, '.', ''), '0'), '.');
        }

        return '0 0 16 16';
    }

    /** El SVG procesado completo (para guardarlo como archivo standalone). */
    public function svgComoString()
    {
        return $this->dom->saveXML($this->svg);
    }

    /** Un <symbol id viewBox> con el contenido del SVG, para el sprite combinado. */
    public function comoSimbolo($id)
    {
        $interno = '';
        foreach ($this->svg->childNodes as $hijo) {
            $interno .= $this->dom->saveXML($hijo);
        }

        // Conserva el fill del svg raíz (en monocromáticos es 'currentColor') para que
        // los hijos sin fill propio lo hereden desde el <symbol> y se puedan teñir.
        $fill = $this->svg->hasAttribute('fill')
              ? ' fill="' . htmlspecialchars($this->svg->getAttribute('fill'), ENT_QUOTES) . '"'
              : '';

        return '<symbol id="' . htmlspecialchars($id, ENT_QUOTES) . '" viewBox="'
             . htmlspecialchars($this->viewBox(), ENT_QUOTES) . '"' . $fill . '>'
             . $interno
             . '</symbol>';
    }

    /**
     * Renombra todos los ids internos del SVG (y sus referencias) con un prefijo único,
     * para evitar colisiones cuando varios SVG se combinan en un mismo sprite.
     * Ej: id="b" -> id="<prefijo>-1", y url(#b) / href="#b" se actualizan en consecuencia.
     *
     * @param string $prefijo Prefijo único (p. ej. el valor/id del símbolo).
     */
    public function prefijarIds($prefijo)
    {
        $mapa     = [];
        $contador = 0;

        // 1. Asigna un id nuevo y único a cada elemento que tenga id.
        foreach ($this->todosLosElementos() as $el) {
            if ($el->hasAttribute('id') && $el->getAttribute('id') !== '') {
                $viejo        = $el->getAttribute('id');
                $nuevo        = $prefijo . '-' . (++$contador);
                $mapa[$viejo] = $nuevo;
                $el->setAttribute('id', $nuevo);
            }
        }

        if (empty($mapa)) {
            return $this;
        }

        // 2. Actualiza las referencias: #id en href/xlink:href y url(#id) en cualquier atributo o estilo.
        foreach ($this->todosLosElementos() as $el) {
            if (!$el->attributes) {
                continue;
            }
            foreach (iterator_to_array($el->attributes) as $attr) {
                $nombre     = $attr->nodeName;
                $valor      = $attr->nodeValue;
                $nuevoValor = $valor;

                foreach ($mapa as $viejo => $nuevo) {
                    if (($nombre === 'href' || $nombre === 'xlink:href') && $valor === '#' . $viejo) {
                        $nuevoValor = '#' . $nuevo;
                        break;
                    }
                    $nuevoValor = str_replace(
                        ['url(#' . $viejo . ')', "url('#" . $viejo . "')", 'url("#' . $viejo . '")'],
                        ['url(#' . $nuevo . ')', "url('#" . $nuevo . "')", 'url("#' . $nuevo . '")'],
                        $nuevoValor
                    );
                }

                if ($nuevoValor !== $valor) {
                    $attr->nodeValue = $nuevoValor;
                }
            }
        }

        return $this;
    }

    // ---------- Auxiliares ----------

    /** Snapshot de todos los elementos (para iterar mientras se modifica el árbol). */
    private function todosLosElementos()
    {
        $lista = [];
        foreach ($this->dom->getElementsByTagName('*') as $el) {
            $lista[] = $el;
        }
        return $lista;
    }

    /** True si el valor es un color "real" (no none/currentColor/transparent/inherit/vacío). */
    private function esColorReal($valor)
    {
        $v = strtolower(trim($valor));
        return !in_array($v, ['', 'none', 'currentcolor', 'transparent', 'inherit'], true);
    }

    /** Color de una propiedad (atributo o estilo inline); null si no aplica. */
    private function colorDe($el, $prop)
    {
        if ($el->hasAttribute($prop)) {
            $v = $el->getAttribute($prop);
            if ($this->esColorReal($v) || stripos($v, 'url(') === 0) {
                return $v;
            }
        }

        if ($el->hasAttribute('style')
            && preg_match('/(?:^|;)\s*' . preg_quote($prop, '/') . '\s*:\s*([^;]+)/i', $el->getAttribute('style'), $m)) {
            $v = trim($m[1]);
            if ($this->esColorReal($v) || stripos($v, 'url(') === 0) {
                return $v;
            }
        }

        return null;
    }

    private function soloNumero($v)
    {
        $v = preg_replace('/[^0-9.]/', '', (string) $v);
        return is_numeric($v) ? (float) $v : 0;
    }
}
