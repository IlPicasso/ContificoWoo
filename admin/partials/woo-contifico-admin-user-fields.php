<h2><?php _e( 'Información Fiscal', $plugin_name ); ?></h2>
<table class="form-table" id="contifico-additional-information">
	<tbody>
	<?php foreach ( $fields as $key => $field ): ?>
		<tr>
			<th>
				<label for="<?php echo $key; ?>"><?php echo $field['label']; ?></label>
			</th>
			<td>
				<?php
				$field['label'] = false;
				$field['class'] = [ 'regular-text' ];
				woocommerce_form_field( $key, $field, $field['value'] );
				?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>