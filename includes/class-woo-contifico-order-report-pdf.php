<?php

// Cargar FPDF antes de declarar la clase para evitar errores de sintaxis en PHP antiguos
if ( ! class_exists( 'FPDF' ) ) {
    $woo_contifico_fpdf_path = dirname( __FILE__ ) . '/../libraries/fpdf.php';

    if ( file_exists( $woo_contifico_fpdf_path ) ) {
        require_once $woo_contifico_fpdf_path;
    }
}

class Woo_Contifico_Order_Report_Pdf {
    private $pages = [];
    private $current_page = 0;
    private $current_y = 0;
    private $margin_left = 40;
    private $max_chars = 105;
    private $images = [];

        $this->margin_left   = 20;
        $this->top_margin    = 16;
        $this->bottom_margin = 16;
        $this->column_gap    = 12;
    }

    function set_branding( $brand_name, $brand_details = array() ) {
        $this->brand_name    = $brand_name;
        $this->brand_details = $brand_details;
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

    function set_document_title( $title ) {
        $this->document_title = $title;
    }

    function set_recipient_block( $heading, $lines ) {
        $this->recipient_heading = $heading;
        $this->recipient_lines   = $lines;
    }

    function set_order_summary( $rows ) {
        $this->order_summary = $rows;
    }

    function add_product_row( $name, $quantity, $details = array() ) {
        $this->product_rows[] = array(
            'name'     => $name,
            'quantity' => $quantity,
            'details'  => $details,
        );
    }

    function add_inventory_movement_line( $text ) {
        $this->movement_lines[] = $text;
    }

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

        $pdf = new FPDF( 'P', 'mm', 'A4' );
        $pdf->SetMargins( $this->margin_left, $this->top_margin, $this->margin_left );
        $pdf->SetAutoPageBreak( true, $this->bottom_margin );
        $pdf->AddPage();

        $this->render_branding( $pdf );
        $this->render_title( $pdf );
        $this->render_info_columns( $pdf );
        $this->render_products_table( $pdf );
        $this->render_movements_section( $pdf );
        $this->render_transfers_section( $pdf );

        return $pdf->Output( 'S' );
    }

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

        $fpdf_path = dirname( __FILE__ ) . '/../libraries/fpdf.php';
        if ( file_exists( $fpdf_path ) ) {
            require_once $fpdf_path;
        }

        if ( ! class_exists( 'FPDF' ) ) {
            die( 'La librería FPDF no se pudo cargar correctamente desde: ' . $fpdf_path );
        }
    }

    function render_branding( $pdf ) {
        $usable_width = $pdf->GetPageWidth() - ( 2 * $this->margin_left );
        $left_width   = $usable_width * 0.5;
        $right_width  = $usable_width * 0.5;
        $start_x      = $pdf->GetX();
        $start_y      = $pdf->GetY();

        $logo_width = 0;
        $logo_height = 0;

        if ( '' !== $this->brand_logo_path && file_exists( $this->brand_logo_path ) ) {
            $logo_max_height = 18;
            $logo_size       = @getimagesize( $this->brand_logo_path );

            if ( is_array( $logo_size ) && isset( $logo_size[0], $logo_size[1] ) && (int) $logo_size[1] > 0 ) {
                $ratio       = $logo_max_height / $logo_size[1];
                $logo_width  = $logo_size[0] * $ratio;
                $logo_height = $logo_max_height;
            } else {
                $logo_width  = 42;
                $logo_height = $logo_max_height;
            }

            $pdf->Image( $this->brand_logo_path, $start_x, $start_y, $logo_width, $logo_height );
        }

        $text_offset = $start_x;

        if ( $logo_width > 0 ) {
            $text_offset += $logo_width + 6;
        }

        $brand_block_end_y = $start_y;

        if ( '' !== $this->brand_name ) {
            $pdf->SetFont( 'Arial', 'B', 16 );
            $pdf->SetXY( $text_offset, $start_y );
            $pdf->Cell( max( 0, $left_width - ( $text_offset - $start_x ) ), 8, $this->encode_text( $this->brand_name ), 0, 1, 'L' );
            $brand_block_end_y = max( $brand_block_end_y, $pdf->GetY() );
        }

        if ( $logo_height > 0 ) {
            $brand_block_end_y = max( $brand_block_end_y, $start_y + $logo_height );
        }

        $details_end_y = $start_y;

        if ( ! empty( $this->brand_details ) ) {
            $pdf->SetFont( 'Arial', '', 10 );
            $pdf->SetXY( $start_x + $left_width, $start_y );
            foreach ( $this->brand_details as $line ) {
                $pdf->Cell( $right_width, 5, $this->encode_text( $line ), 0, 2, 'R' );
            }
            $details_end_y = $pdf->GetY();
        }

    private function add_page() : void {
        $this->pages[]     = [
            'commands' => [],
            'xobjects' => [],
        ];
        $this->current_page = count( $this->pages ) - 1;
        $this->current_y    = 780;
    }

    function render_title( $pdf ) {
        if ( '' === $this->document_title ) {
            return;
        }

        $this->ensure_space( $line_height );
        $text = $this->escape_text( $text );
        $y    = max( 40, $this->current_y );
        $this->pages[ $this->current_page ]['commands'][] = sprintf( 'BT /F1 %d Tf %d %d Td (%s) Tj ET', $font_size, $this->margin_left, $y, $text );
        $this->current_y -= $line_height;
    }

    function render_products_table( $pdf ) {
        if ( empty( $this->product_rows ) ) {
            return;
        }

        $usable_width = $pdf->GetPageWidth() - ( 2 * $this->margin_left );
        $product_col  = $usable_width * 0.7;
        $qty_col      = $usable_width * 0.3;

        $pdf->SetFont( 'Arial', 'B', 11 );
        $pdf->SetFillColor( 240, 240, 240 );
        $product_label = function_exists( '__' ) ? __( 'Producto', 'woo-contifico' ) : 'Producto';
        $qty_label     = function_exists( '__' ) ? __( 'Cantidad', 'woo-contifico' ) : 'Cantidad';
        $pdf->Cell( $product_col, 9, $this->encode_text( $product_label ), 0, 0, 'L', true );
        $pdf->Cell( $qty_col, 9, $this->encode_text( $qty_label ), 0, 1, 'R', true );

        $pdf->SetFont( 'Arial', '', 10 );
        $pdf->SetDrawColor( 220, 220, 220 );

        foreach ( $this->product_rows as $row ) {
            $name     = isset( $row['name'] ) ? (string) $row['name'] : '';
            $quantity = isset( $row['quantity'] ) ? (string) $row['quantity'] : '';
            $details  = isset( $row['details'] ) && is_array( $row['details'] ) ? $row['details'] : array();

            $x_start   = $pdf->GetX();
            $y_start   = $pdf->GetY();
            $line_text = $this->encode_text( $name );

            if ( ! empty( $details ) ) {
                $line_text .= "\n" . $this->encode_text( implode( "\n", $details ) );
            }

            $pdf->MultiCell( $product_col, 5.5, $line_text, 0, 'L' );
            $y_after_product = $pdf->GetY();

            $pdf->SetXY( $x_start + $product_col, $y_start );
            $row_height = $y_after_product - $y_start;
            $pdf->Cell( $qty_col, $row_height, $this->encode_text( $quantity ), 0, 0, 'R' );
            $pdf->Ln( 0 );

            $max_y = max( $y_after_product, $y_start + $row_height );
            $pdf->SetY( $max_y );

            $line_y = $pdf->GetY() + 1;
            $pdf->Line( $this->margin_left, $line_y, $pdf->GetPageWidth() - $this->margin_left, $line_y );
            $pdf->Ln( 3 );
        }

        $pdf->Ln( 4 );
    }

    function render_movements_section( $pdf ) {
        if ( empty( $this->movement_lines ) ) {
            return;
        }

        $pdf->SetFont( 'Arial', 'B', 11 );
        $title = function_exists( '__' ) ? __( 'Movimientos de inventario', 'woo-contifico' ) : 'Movimientos de inventario';
        $pdf->Cell( 0, 7, $this->encode_text( $title ), 0, 1, 'L' );
        $pdf->SetFont( 'Arial', '', 10 );

        foreach ( $this->movement_lines as $line ) {
            $pdf->Cell( 4, 5.5, chr( 149 ), 0, 0, 'L' );
            $pdf->MultiCell( 0, 5.5, $this->encode_text( $line ), 0, 'L' );
        }

        $pdf->Ln( 2 );
    }

    function render_transfers_section( $pdf ) {
        if ( empty( $this->transfer_lines ) ) {
            return;
        }

        $pdf->SetFont( 'Arial', 'B', 11 );
        $title = function_exists( '__' ) ? __( 'Transferencias registradas', 'woo-contifico' ) : 'Transferencias registradas';
        $pdf->Cell( 0, 7, $this->encode_text( $title ), 0, 1, 'L' );
        $pdf->SetFont( 'Arial', '', 10 );

        foreach ( $this->transfer_lines as $line ) {
            $pdf->Cell( 4, 5.5, chr( 149 ), 0, 0, 'L' );
            $pdf->MultiCell( 0, 5.5, $this->encode_text( $line ), 0, 'L' );
        }
    }

    function encode_text( $text ) {
        $text = preg_replace( "/[\n\r]/", "\n", $text );

        return $this->to_win1252( $text );
    }

    function to_win1252( $text ) {
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
