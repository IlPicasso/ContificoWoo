/* global jQuery */
(function( $ ) {
	'use strict';

	$(function() {

		const taxType = $('#tax_type');

		// Initialize Tax Type
		taxType.select2();

		$('#billing_country').on('change', function () {
			const country = this.value.toLowerCase();

			if (country === 'ec') {
				$("#tax_type_field, #tax_id_field").addClass('validate-required').removeClass('woocommerce-validated');
				$("#tax_type_field abbr.required, #tax_id_field abbr.required").show();
			} else {
				$("#tax_type_field, #tax_id_field").removeClass('validate-required');
				$("#tax_type_field abbr.required, #tax_id_field abbr.required").hide();
			}
		}).change();

		$('#tax_subject').on('change', function () {
			if ($(this).prop('checked')) {
				$('#billing_company_field').addClass('validate-required');
			} else {
				$('#billing_company_field').removeClass('validate-required');
			}
		}).change();

		$('#taxpayer_type_field input[name="taxpayer_type"]').on('change', function(){
			if( $(this).val() === 'N' ) {
				taxType.children('option').removeAttr('disabled');
				taxType.val('cedula');
			}
			else {
				taxType.children('option').attr('disabled', 'disabled');
				taxType.children('option[value="ruc"]').removeAttr('disabled');
				taxType.val('ruc');
			}
			taxType.select2();
		});

	});
})( jQuery );
