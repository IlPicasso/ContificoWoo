<?php

// Cargar FPDF antes de declarar la clase para evitar errores de sintaxis en PHP antiguos
if ( ! class_exists( 'FPDF' ) ) {
    $woo_contifico_fpdf_path = dirname( __FILE__ ) . '/../libraries/fpdf.php';

    if ( file_exists( $woo_contifico_fpdf_path ) ) {
        require_once $woo_contifico_fpdf_path;
    }
}

class Woo_Contifico_Order_Report_Pdf {
    var $brand_name;
    var $brand_details;
    var $brand_logo_path;
    var $document_title;

    var $recipient_heading;
    var $recipient_lines;

    var $order_summary;
    var $product_rows;
    var $movement_lines;
    var $transfer_lines;

    var $margin_left;   // mm
    var $top_margin;    // mm
    var $bottom_margin; // mm
    var $column_gap;    // mm

    // PHP 4 compatibility
    function Woo_Contifico_Order_Report_Pdf() {
        $this->__construct();
    }

    function __construct() {
        $this->brand_name      = '';
        $this->brand_details   = array();
        $this->brand_logo_path = '';
        $this->document_title  = '';

        $this->recipient_heading = '';
        $this->recipient_lines   = array();

        $this->order_summary  = array();
        $this->product_rows   = array();
        $this->movement_lines = array();
        $this->transfer_lines = array();

        $this->margin_left   = 20;
        $this->top_margin    = 16;
        $this->bottom_margin = 16;
        $this->column_gap    = 12;
    }

    function set_branding( $brand_name, $brand_details = array() ) {
        $this->brand_name    = $brand_name;
        $this->brand_details = $brand_details;
    }

    function set_brand_logo_path( $logo_path ) {
        $this->brand_logo_path = $logo_path;
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

    function add_transfer_summary_line( $text ) {
        $this->transfer_lines[] = $text;
    }

    function render() {
        $this->require_fpdf();

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

    function require_fpdf() {
        if ( class_exists( 'FPDF' ) ) {
            return;
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
        $start_x      = $pdf->GetX();
        $start_y      = $pdf->GetY();

        $logo_width  = 0;
        $logo_height = 0;

        if ( '' !== $this->brand_logo_path && file_exists( $this->brand_logo_path ) ) {
            $logo_max_height = 18;
            $logo_max_width  = 48;
            $logo_size       = @getimagesize( $this->brand_logo_path );

            if ( is_array( $logo_size ) && isset( $logo_size[0], $logo_size[1] ) && (int) $logo_size[0] > 0 && (int) $logo_size[1] > 0 ) {
                $ratio       = min( $logo_max_width / $logo_size[0], $logo_max_height / $logo_size[1] );
                $logo_width  = $logo_size[0] * $ratio;
                $logo_height = $logo_size[1] * $ratio;
            } else {
                $logo_width  = $logo_max_width;
                $logo_height = $logo_max_height;
            }

            $pdf->Image( $this->brand_logo_path, $start_x, $start_y, $logo_width, $logo_height );
        }

        $text_offset = $start_x;

        if ( $logo_width > 0 ) {
            $text_offset += $logo_width + 6;
        }

        $text_width = $usable_width - ( $text_offset - $start_x );

        $brand_block_end_y = $start_y;

        if ( '' !== $this->brand_name ) {
            $pdf->SetFont( 'Arial', 'B', 16 );
            $pdf->SetXY( $text_offset, $start_y );
            $pdf->MultiCell( $text_width, 8, $this->encode_text( $this->brand_name ), 0, 'L' );
            $brand_block_end_y = max( $brand_block_end_y, $pdf->GetY() );
        }

        if ( ! empty( $this->brand_details ) ) {
            $pdf->SetFont( 'Arial', '', 10 );
            $pdf->SetXY( $text_offset, $brand_block_end_y + 1 );

            foreach ( $this->brand_details as $line ) {
                $pdf->MultiCell( $text_width, 5, $this->encode_text( $line ), 0, 'L' );
            }

            $brand_block_end_y = max( $brand_block_end_y, $pdf->GetY() );
        }

        if ( $logo_height > 0 ) {
            $brand_block_end_y = max( $brand_block_end_y, $start_y + $logo_height );
        }

        $pdf->SetY( $brand_block_end_y + 12 );
    }

    function render_title( $pdf ) {
        if ( '' === $this->document_title ) {
            return;
        }

        $pdf->SetFont( 'Arial', 'B', 20 );
        $pdf->Cell( 0, 12, $this->encode_text( $this->document_title ), 0, 1, 'L' );
        $pdf->Ln( 4 );
    }

    function render_info_columns( $pdf ) {
        $usable_width = $pdf->GetPageWidth() - ( 2 * $this->margin_left );
        $column_width = ( $usable_width - $this->column_gap ) / 2;
        $start_x      = $pdf->GetX();
        $start_y      = $pdf->GetY();
        $max_y        = $start_y;

        // Recipient / address block.
        $pdf->SetFont( 'Arial', 'B', 11 );
        $pdf->Cell( $column_width, 6, $this->encode_text( $this->recipient_heading ), 0, 1, 'L' );
        $pdf->SetFont( 'Arial', '', 10 );
        foreach ( $this->recipient_lines as $line ) {
            $pdf->Cell( $column_width, 5.5, $this->encode_text( $line ), 0, 1, 'L' );
        }
        $max_y = max( $max_y, $pdf->GetY() );

        // Order summary block.
        $pdf->SetXY( $start_x + $column_width + $this->column_gap, $start_y );
        if ( ! empty( $this->order_summary ) ) {
            $pdf->SetFont( 'Arial', 'B', 11 );
            $title = function_exists( '__' ) ? __( 'Detalle del pedido', 'woo-contifico' ) : 'Detalle del pedido';
            $pdf->Cell( $column_width, 6, $this->encode_text( $title ), 0, 1, 'L' );
            $pdf->SetFont( 'Arial', '', 10 );
            foreach ( $this->order_summary as $row ) {
                $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                $value = isset( $row['value'] ) ? (string) $row['value'] : '';
                $pdf->Cell( $column_width * 0.55, 5.5, $this->encode_text( $label ), 0, 0, 'L' );
                $pdf->Cell( $column_width * 0.45, 5.5, $this->encode_text( $value ), 0, 1, 'L' );
            }
            $max_y = max( $max_y, $pdf->GetY() );
        }

        $pdf->SetY( $max_y + 6 );
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
