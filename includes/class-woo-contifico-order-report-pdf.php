<?php

class Woo_Contifico_Order_Report_Pdf {
    private $pages = [];
    private $current_page = 0;
    private $current_y = 0;
    private $margin_left = 40;
    private $max_chars = 105;

    public function __construct() {
        $this->add_page();
    }

    public function add_title( $text ) {
        $this->add_wrapped_text( $text, 16, 28 );
        $this->add_spacer( 6 );
    }

    public function add_subheading( $text ) {
        $this->add_wrapped_text( $text, 13, 20 );
    }

    private function add_wrapped_text( $text, $font_size, $line_height ) {
        foreach ( $this->wrap_text( $text ) as $line ) {
            $this->add_line( $line, $font_size, $line_height );
        }
    }

    public function add_text_line( $text, $font_size = 11, $line_height = 14 ) {
        foreach ( $this->wrap_text( $text ) as $line ) {
            $this->add_line( $line, $font_size, $line_height );
        }
    }

    public function add_list_item( $text ) {
        $this->add_text_line( '- ' . $text );
    }

    public function add_spacer( $height = 10 ) {
        $this->ensure_space( $height );
        $this->current_y -= $height;
    }

    public function render() {
        $objects  = [];
        $offsets  = [];
        $next_id  = 1;
        $font_obj = $next_id++;
        $objects[ $font_obj ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $page_objects = [];
        $kid_refs     = [];

        foreach ( $this->pages as $commands ) {
            $stream = implode( "\n", $commands );

            if ( '' === trim( $stream ) ) {
                $stream = 'BT /F1 12 Tf 40 760 Td ( ) Tj ET';
            }

            $content_id = $next_id++;
            $objects[ $content_id ] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", strlen( $stream ), $stream );

            $page_template  = '<< /Type /Page /Parent __PARENT__ /MediaBox [0 0 612 792] /Resources << /Font << /F1 ' . $font_obj . ' 0 R >> >> /Contents ' . $content_id . ' 0 R >>';
            $page_object_id = $next_id++;
            $objects[ $page_object_id ] = $page_template;
            $page_objects[]             = $page_object_id;
            $kid_refs[]                 = $page_object_id . ' 0 R';
        }

        $pages_object_id = $next_id++;
        $objects[ $pages_object_id ] = sprintf( '<< /Type /Pages /Count %d /Kids [ %s ] >>', count( $kid_refs ), implode( ' ', $kid_refs ) );

        foreach ( $page_objects as $page_object_id ) {
            $objects[ $page_object_id ] = str_replace( '__PARENT__', $pages_object_id . ' 0 R', $objects[ $page_object_id ] );
        }

        $catalog_id = $next_id++;
        $objects[ $catalog_id ] = '<< /Type /Catalog /Pages ' . $pages_object_id . ' 0 R >>';

        $pdf = "%PDF-1.4\n";

        for ( $i = 1; $i < $next_id; $i++ ) {
            $offsets[ $i ] = strlen( $pdf );
            $pdf .= $i . " 0 obj\n" . $objects[ $i ] . "\nendobj\n";
        }

        $xref_offset = strlen( $pdf );
        $pdf        .= 'xref\n0 ' . $next_id . "\n";
        $pdf        .= "0000000000 65535 f \n";

        for ( $i = 1; $i < $next_id; $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
        }

        $pdf .= 'trailer\n<< /Size ' . $next_id . ' /Root ' . $catalog_id . " 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF";

        return $pdf;
    }

    private function add_page() {
        $this->pages[]     = [];
        $this->current_page = count( $this->pages ) - 1;
        $this->current_y    = 780;
    }

    private function add_line( $text, $font_size, $line_height ) {
        if ( '' === trim( $text ) ) {
            $this->add_spacer( $line_height );
            return;
        }

        $this->ensure_space( $line_height );
        $text = $this->escape_text( $text );
        $y    = max( 40, $this->current_y );
        $this->pages[ $this->current_page ][] = sprintf( 'BT /F1 %d Tf %d %d Td (%s) Tj ET', $font_size, $this->margin_left, $y, $text );
        $this->current_y -= $line_height;
    }

    private function wrap_text( $text ) {
        $text    = $this->normalize_text( $text );
        $chunks  = preg_split( "/\r?\n/", $text );
        $results = [];

        foreach ( $chunks as $chunk ) {
            $chunk = trim( $chunk );

            if ( '' === $chunk ) {
                $results[] = '';
                continue;
            }

            $wrapped = wordwrap( $chunk, $this->max_chars, "\n", true );
            $results = array_merge( $results, explode( "\n", $wrapped ) );
        }

        return $results ?: [ '' ];
    }

    private function ensure_space( $height ) {
        if ( $this->current_y - $height < 40 ) {
            $this->add_page();
        }
    }

    private function normalize_text( $text ) {
        $text = preg_replace( "/[\t\r]+/", ' ', $text );
        $text = preg_replace( "/\s+/", ' ', $text );
        return trim( $text );
    }

    private function escape_text( $text ) {
        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $text );
            if ( false !== $converted ) {
                $text = $converted;
            }
        }

        $text = str_replace( [ '\\', '(', ')' ], [ '\\\\', '\\(', '\\)' ], $text );
        return preg_replace( "/[\n\r]/", ' ', $text );
    }
}
