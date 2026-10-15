/**
 * WPCalibrate Marketplace Review Importer - Admin JavaScript
 */
/* global jQuery, wpcmriData */

(function($) {
	'use strict';

	$(function() {
		// Update selected source card state and reveal configuration panel.
		function updateSourceSelection() {
			var $checkedRadio = $('input[name="source_type"]:checked');
			if (!$checkedRadio.length) {
				return;
			}

			var selectedSource = $checkedRadio.val();

			// Update card visual state.
			$('.wpcmri-source-card').removeClass('is-selected');
			$checkedRadio.closest('.wpcmri-source-card').addClass('is-selected');

			// Toggle panel display.
			$('.wpcmri-source-panel').hide();
			if ('csv' === selectedSource) {
				$('#wpcmri-panel-csv').fadeIn(150);
			} else if ('json' === selectedSource) {
				$('#wpcmri-panel-json').fadeIn(150);
			} else if ('serpapi' === selectedSource) {
				$('#wpcmri-panel-serpapi').fadeIn(150);
			}
		}

		// Handle radio changes directly and when clicking label cards.
		$(document).on('change', 'input[name="source_type"]', function() {
			updateSourceSelection();
		});

		// Trigger initial state.
		updateSourceSelection();

		// Show filename when chosen in dropzones.
		$('.wpcmri-file-input').on('change', function() {
			var files = this.files;
			if (files && files.length > 0) {
				var fileName = files[0].name;
				var $dropzone = $(this).closest('.wpcmri-dropzone');
				var $hint = $dropzone.find('.wpcmri-form-hint');
				if (!$hint.length) {
					$hint = $('<p class="wpcmri-form-hint"></p>').appendTo($dropzone);
				}
				$hint.html('<strong>Selected file:</strong> ' + $('<div>').text(fileName).html());
			}
		});

		// Masked credential click-to-clear.
		$('#wpcmri_provider_serpapi_api_key, #wpcmri_license_key').on('focus', function() {
			if ($(this).val().indexOf('***') !== -1) {
				$(this).val('');
			}
		});

		// 1-Click Demo JSON reviews loader.
		$('#wpcmri-load-demo-json-btn').on('click', function(e) {
			e.preventDefault();
			if (window.wpcmriData && window.wpcmriData.demoJson) {
				var $textarea = $('#raw_json');
				$textarea.val(window.wpcmriData.demoJson);
				$textarea.css('border-color', '#2563eb');

				var $btn = $(this);
				var originalHtml = $btn.html();
				var loadedMsg = (window.wpcmriData.strings && window.wpcmriData.strings.demoLoaded) ? window.wpcmriData.strings.demoLoaded : '15 demo reviews loaded!';
				$btn.html('<span class="dashicons dashicons-yes-alt" style="color:#059669;"></span> ' + loadedMsg);
				setTimeout(function() {
					$btn.html(originalHtml);
					$textarea.css('border-color', '');
				}, 2500);
			}
		});


		// Handle button loading state on preview submission.
		$('.wpcmri-form').on('submit', function() {
			var $btn = $(this).find('button[type="submit"]');
			if ($btn.length && !$btn.prop('disabled')) {
				$btn.data('original-text', $btn.html());
				$btn.css('opacity', '0.7');
			}
		});
	});
})(jQuery);
