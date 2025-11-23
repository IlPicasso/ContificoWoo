<?php

class Woo_Contifico_Order_Report_Pdf {
    private $entries = [];
    private $max_chars = 105;
    private $margin_left = 40;
    private $top_margin = 40;
    private $bottom_margin = 40;

    public function __construct() {
        // Defer PDF creation to render(); we only collect content here.
    }

    public function add_title( string $text ) : void {
        $this->add_wrapped_text( $text, 16, 28 );
        $this->add_spacer( 6 );
    }

    public function add_subheading( string $text ) : void {
        $this->add_wrapped_text( $text, 13, 20 );
    }

    public function add_text_line( string $text, int $font_size = 11, int $line_height = 14 ) : void {
        $this->add_wrapped_text( $text, $font_size, $line_height );
    }

    public function add_list_item( string $text ) : void {
        $this->add_text_line( '- ' . $text );
    }

    public function add_spacer( int $height = 10 ) : void {
        $this->entries[] = [ 'type' => 'spacer', 'height' => $height ];
    }

    public function render() : string {
        $objects  = [];
        $offsets  = [];
        $next_id  = 1;
        $font_obj = $next_id++;
        $objects[ $font_obj ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        $page_objects = [];
        $kid_refs     = [];

        foreach ( $this->pages as $commands ) {
            $stream = implode( "\n", $commands );

            if ( '' === trim( $stream ) ) {
                $stream = 'BT /F1 12 Tf 40 760 Td <> Tj ET';
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

        $pdf = new FPDF( 'P', 'pt', 'Letter' );
        $pdf->SetMargins( $this->margin_left, $this->top_margin, $this->margin_left );
        $pdf->SetAutoPageBreak( true, $this->bottom_margin );
        $pdf->AddPage();

        foreach ( $this->entries as $entry ) {
            if ( 'spacer' === $entry['type'] ) {
                $this->output_spacer( $pdf, $entry['height'] );
                continue;
            }

            $this->output_line( $pdf, $entry['text'], $entry['font_size'], $entry['line_height'] );
        }

        return $pdf->Output( 'S' );
    }

    private function output_spacer( FPDF $pdf, int $height ) : void {
        $pdf->SetY( $pdf->GetY() + $height );
    }

    private function output_line( FPDF $pdf, string $text, int $font_size, int $line_height ) : void {
        $pdf->SetFont( 'Arial', '', $font_size );
        $pdf->Cell( 0, $line_height, $this->encode_text( $text ), 0, 1, 'L' );
    }

    private function add_wrapped_text( string $text, int $font_size, int $line_height ) : void {
        foreach ( $this->wrap_text( $text ) as $line ) {
            $this->entries[] = [
                'type'        => 'text',
                'text'        => $line,
                'font_size'   => $font_size,
                'line_height' => $line_height,
            ];
        }
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

    private function normalize_text( string $text ) : string {
        $text = preg_replace( "/[\t\r]+/", ' ', $text );
        $text = preg_replace( "/\s+/", ' ', $text );
        return trim( $text );
    }

    private function encode_text( string $text ) : string {
        $text = preg_replace( "/[\n\r]/", ' ', $text );

        $win_1252 = $this->to_win1252( $text );

        return bin2hex( $win_1252 );
    }

    private function to_win1252( string $text ) : string {
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $converted = @mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
            if ( false !== $converted ) {
                return $converted;
            }
        }

        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
            if ( false !== $converted ) {
                return $converted;
            }
        }

        return $text;
    }
}
