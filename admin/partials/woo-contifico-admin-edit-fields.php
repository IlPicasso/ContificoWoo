<?php
    /**
     * @var $invoice_name
     * @var $tax_label
     * @var $tax_id
     * @var $tax_payer
     * @var $tax_subject
     * @var $taxpayer_type
     */
?>
<div class="address">
	<strong><?php echo $invoice_name ?></strong>
	<p>
		<strong><?php echo $tax_label ?></strong>
		<?php echo $tax_id ?>
	</p>
    <p>
        <strong><?php _e('Tipo de Contribuyente:',$this->plugin_name) ?></strong>
		<?php echo $tax_payer ?>
    </p>
</div>
<div class="edit_address"><?php

	foreach( $fields as $key => $field ) {

		$field['id'] = $key;
		switch( $field['type'] ) {
			case 'select':
				woocommerce_wp_select( $field );
				break;
			case 'text':
				woocommerce_wp_text_input( $field );
				break;
			case 'checkbox':
				$field['cbvalue'] = 1;
				$field['value'] = $tax_subject;
				$field['style'] = "width:auto;";
				woocommerce_wp_checkbox( $field );
				break;
			case 'radio':
				$field['value'] = $taxpayer_type;
				$field['style'] = "width:auto;";
				woocommerce_wp_radio( $field );
				break;
		}

	}

	?>
</div>