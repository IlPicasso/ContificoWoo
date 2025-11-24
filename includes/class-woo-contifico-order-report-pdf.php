<?php

class Woo_Contifico_Order_Report_Pdf {
    private $pages = [];
    private $current_page = 0;
    private $current_y = 0;
    private $margin_left = 40;
    private $max_chars = 105;
    private $images = [];

    public function __construct() {
        $this->add_page();
    }

    public function add_title( string $text ) : void {
        $this->add_wrapped_text( $text, 16, 28 );
        $this->add_spacer( 6 );
    }

    public function add_subheading( string $text ) : void {
        $this->add_wrapped_text( $text, 13, 20 );
        $this->add_spacer( 4 );
    }

    public function add_image( string $image_data, int $image_width, int $image_height, int $max_width = 160 ) : void {
        if ( '' === $image_data || $image_width <= 0 || $image_height <= 0 ) {
            return;
        }

        $scale             = min( 1, $max_width / $image_width );
        $display_width     = (int) round( $image_width * $scale );
        $display_height    = (int) round( $image_height * $scale );
        $image_hash        = md5( $image_data );
        $image_object_name = null;

        if ( ! isset( $this->images[ $image_hash ] ) ) {
            $image_object_name          = 'Im' . ( count( $this->images ) + 1 );
            $this->images[ $image_hash ] = [
                'name'   => $image_object_name,
                'data'   => $image_data,
                'width'  => $image_width,
                'height' => $image_height,
            ];
        } else {
            $image_object_name = $this->images[ $image_hash ]['name'];
        }

        $this->ensure_space( $display_height + 10 );
        $y_position = max( 40, $this->current_y - $display_height );
        $x_position = $this->margin_left;

        $this->pages[ $this->current_page ]['commands'][] = sprintf(
            'q %1$d 0 0 %2$d %3$d %4$d cm /%5$s Do Q',
            $display_width,
            $display_height,
            $x_position,
            $y_position,
            $image_object_name
        );

        $this->pages[ $this->current_page ]['xobjects'][] = $image_object_name;
        $this->current_y                                   = $y_position - 10;
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

        foreach ( $this->images as $hash => $image ) {
            $image_obj_id                = $next_id++;
            $this->images[ $hash ]['id'] = $image_obj_id;
            $objects[ $image_obj_id ]    = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $image['width'],
                $image['height'],
                strlen( $image['data'] ),
                $image['data']
            );
        }

        $page_objects = [];
        $kid_refs     = [];

        foreach ( $this->pages as $page ) {
            $commands = isset( $page['commands'] ) ? $page['commands'] : [];
            $stream   = implode( "\n", $commands );

            if ( '' === trim( $stream ) ) {
                $stream = 'BT /F1 12 Tf 40 760 Td ( ) Tj ET';
            }

            $content_id = $next_id++;
            $objects[ $content_id ] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", strlen( $stream ), $stream );

            $resource_parts = [ '/Font << /F1 ' . $font_obj . ' 0 R >>' ];

            if ( isset( $page['xobjects'] ) && ! empty( $page['xobjects'] ) ) {
                $xobject_entries = [];

                foreach ( array_unique( $page['xobjects'] ) as $object_name ) {
                    foreach ( $this->images as $image ) {
                        if ( $image['name'] === $object_name && isset( $image['id'] ) ) {
                            $xobject_entries[] = sprintf( '/%1$s %2$d 0 R', $object_name, $image['id'] );
                            break;
                        }
                    }
                }

                if ( ! empty( $xobject_entries ) ) {
                    $resource_parts[] = '/XObject << ' . implode( ' ', $xobject_entries ) . ' >>';
                }
            }

            $resource_block = '/Resources << ' . implode( ' ', $resource_parts ) . ' >>';
            $page_template  = '<< /Type /Page /Parent __PARENT__ /MediaBox [0 0 612 792] ' . $resource_block . ' /Contents ' . $content_id . ' 0 R >>';
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

    private function add_page() : void {
        $this->pages[]     = [
            'commands' => [],
            'xobjects' => [],
        ];
        $this->current_page = count( $this->pages ) - 1;
        $this->current_y    = 780;
    }

    private function add_line( string $text, int $font_size, int $line_height ) : void {
        if ( '' === trim( $text ) ) {
            $this->add_spacer( $line_height );
            return;
        }

        $this->ensure_space( $line_height );
        $text = $this->escape_text( $text );
        $y    = max( 40, $this->current_y );
        $this->pages[ $this->current_page ]['commands'][] = sprintf( 'BT /F1 %d Tf %d %d Td (%s) Tj ET', $font_size, $this->margin_left, $y, $text );
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

    private function escape_text( string $text ) : string {
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
