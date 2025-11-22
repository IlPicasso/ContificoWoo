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

    public function add_title( string $text ) : void {
        $this->add_wrapped_text( $text, 16, 28 );
        $this->add_spacer( 6 );
    }

    public function add_subheading( string $text ) : void {
        $this->add_wrapped_text( $text, 13, 20 );
    }

    private function add_wrapped_text( string $text, int $font_size, int $line_height ) : void {
        foreach ( $this->wrap_text( $text ) as $line ) {
            $this->add_line( $line, $font_size, $line_height );
        }
    }

    public function add_text_line( string $text, int $font_size = 11, int $line_height = 14 ) : void {
        foreach ( $this->wrap_text( $text ) as $line ) {
            $this->add_line( $line, $font_size, $line_height );
        }
    }

    public function add_list_item( string $text ) : void {
        $this->add_text_line( '- ' . $text );
    }

    public function add_spacer( int $height = 10 ) : void {
        $this->ensure_space( $height );
        $this->current_y -= $height;
    }

    public function render() : string {
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
                $stream = 'BT /F1 12 Tf 40 760 Td <FEFF> Tj ET';
            }

            $content_id = $next_id++;
            $objects[ $content_id ] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", $this->length_in_bytes( $stream ), $stream );

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
            $offsets[ $i ] = $this->length_in_bytes( $pdf );
            $pdf .= $i . " 0 obj\n" . $objects[ $i ] . "\nendobj\n";
        }

        $xref_offset = $this->length_in_bytes( $pdf );
        $pdf        .= 'xref\n0 ' . $next_id . "\n";
        $pdf        .= "0000000000 65535 f \n";

        for ( $i = 1; $i < $next_id; $i++ ) {
            $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
        }

        $pdf .= 'trailer\n<< /Size ' . $next_id . ' /Root ' . $catalog_id . " 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF";

        return $pdf;
    }

    private function add_page() : void {
        $this->pages[]     = [];
        $this->current_page = count( $this->pages ) - 1;
        $this->current_y    = 780;
    }

    private function add_line( string $text, int $font_size, int $line_height ) : void {
        if ( '' === trim( $text ) ) {
            $this->add_spacer( $line_height );
            return;
        }

        $this->ensure_space( $line_height );
        $encoded_text = $this->encode_text( $text );
        $y            = max( 40, $this->current_y );
        $this->pages[ $this->current_page ][] = sprintf( 'BT /F1 %d Tf %d %d Td <%s> Tj ET', $font_size, $this->margin_left, $y, $encoded_text );
        $this->current_y -= $line_height;
    }

    private function wrap_text( string $text ) : array {
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

    private function ensure_space( int $height ) : void {
        if ( $this->current_y - $height < 40 ) {
            $this->add_page();
        }
    }

    private function normalize_text( string $text ) : string {
        $text = preg_replace( "/[\t\r]+/", ' ', $text );
        $text = preg_replace( "/\s+/", ' ', $text );
        return trim( $text );
    }

    private function encode_text( string $text ) : string {
        $text = preg_replace( "/[\n\r]/", ' ', $text );

        $utf16be = $this->to_utf16be( $text );

        return bin2hex( "\xFE\xFF" . $utf16be );
    }

    private function to_utf16be( string $text ) : string {
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $converted = @mb_convert_encoding( $text, 'UTF-16BE', 'UTF-8' );
            if ( false !== $converted ) {
                return $converted;
            }
        }

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'UTF-16BE//IGNORE', $text );
            if ( false !== $converted ) {
                return $converted;
            }
        }

        return $text;
    }

    private function length_in_bytes( string $value ) : int {
        if ( function_exists( 'mb_strlen' ) ) {
            return (int) mb_strlen( $value, '8bit' );
        }

        return strlen( $value );
    }
}
