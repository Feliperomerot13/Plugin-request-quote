jQuery(document).ready(function ($) {

    function showStep(container, contact) {
        container.find('.lqc-items-list, .lqc-continue').prop('hidden', contact);
        container.find('.lqc-contact-form').prop('hidden', !contact);
    }

    $(document).on('click', '.lqc-continue, .lqc-back', function () {
        var container = $(this).closest('.lqc-modal-body, .lqc-cart-wrapper');
        var contact = $(this).hasClass('lqc-continue');
        showStep(container, contact);
        container.find(contact ? '.lqc-contact-form input' : '.lqc-continue').first().trigger('focus');
    });

    function refreshView(html) {
        $('.lqc-modal-body, .lqc-cart-wrapper').each(function () {
            var container = $(this);
            var form = container.find('.lqc-contact-form');
            if (form.data('submitting')) return;
            var values = form.serializeArray();
            var inContact = form.length && !form.prop('hidden');
            var requestId = form.data('request-id');
            container.html(html);
            var next = container.find('.lqc-contact-form');
            if (requestId) next.data('request-id', requestId);
            values.forEach(function (field) {
                next.find('[name]').filter(function () { return this.name === field.name; }).val(field.value);
            });
            showStep(container, inContact);
        });
    }

    // AJAX Fragment Refresh for caching
    function refreshCartFragments() {
        $.post(lqc_vars.ajax_url, {
            action: 'lqc_get_fragments'
        }, function(response) {
            if (response.success && response.data) {
                updateCounter(response.data.count);
                if (response.data.html) {
                    refreshView(response.data.html);
                }
                if (response.data.nonce) {
                    lqc_vars.nonce = response.data.nonce;
                }
            }
        });
    }

    // Bypass cache immediately on load
    refreshCartFragments();
    $(document).on('click', '.lqc-add-to-quote', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var product_id = $btn.data('product_id');
        var quantity = 1;
        var variation_id = 0;
        var variations = {};

        // Find product container to scope the search
        var $productContainer = $btn.closest('.product');
        if (!$productContainer.length) {
            $productContainer = $('body'); // fallback to body if not in standard markup
        }

        // Check if there's a quantity input nearby
        var $qtyInput = $btn.closest('form').find('input[name="quantity"]');
        if (!$qtyInput.length) {
            $qtyInput = $productContainer.find('input[name="quantity"]');
        }
        if ($qtyInput.length && $qtyInput.val() > 0) {
            quantity = $qtyInput.val();
        }
        var $form = $btn.closest('form.cart');
        var $shortcodeContainer = $btn.closest('.lqc-shortcode-container');

        if ($form.length) {
            var qty_val = $form.find('input[name="quantity"]').val();
            if (qty_val) quantity = qty_val;

            if ($form.hasClass('variations_form')) {
                var var_id_val = $form.find('input[name="variation_id"]').val();
                if (var_id_val && var_id_val !== '' && var_id_val !== '0') {
                    variation_id = var_id_val;
                    $form.find('select[name^="attribute_"], input[name^="attribute_"]:checked, input[type="hidden"][name^="attribute_"]').each(function () {
                        if ($(this).val()) {
                            variations[$(this).attr('name')] = $(this).val();
                        }
                    });
                } else {
                    alert('Por favor selecciona las opciones del producto antes de agregarlo a la cotización.');
                    return;
                }
            }
        } else if ($shortcodeContainer.length) {
            var missingSelection = false;
            $shortcodeContainer.find('.lqc-shortcode-variation-select').each(function() {
                var val = $(this).val();
                if (!val) {
                    missingSelection = true;
                } else {
                    variations[$(this).data('attribute_name')] = val;
                }
            });

            if (missingSelection) {
                alert('Por favor selecciona las opciones del producto antes de agregarlo a la cotización.');
                return;
            }
        }

        $btn.addClass('loading').prop('disabled', true);

        $.post(lqc_vars.ajax_url, {
            action: 'lqc_add_to_quote',
            nonce: lqc_vars.nonce,
            product_id: product_id,
            quantity: quantity,
            variation_id: variation_id,
            variations: variations
        }, function (response) {
            $btn.removeClass('loading');
            if (response.success) {
                $btn.text(lqc_vars.strings.added);
                updateCounter(response.data.count);

                // Update Modal Content with fresh HTML
                if (response.data.html) {
                    refreshView(response.data.html);
                }

                // Optional: Open modal immediately?
                // $('body').addClass('lqc-modal-open');
            } else {
                alert(response.data.message);
            }
        }).fail(function () {
            alert('No se pudo confirmar la selección. Revisa tu lista antes de intentarlo otra vez.');
        }).always(function () {
            $btn.removeClass('loading').prop('disabled', false);
        });
    });

    // Update Counter
    function updateCounter(count) {
        $('.lqc-count').text(count);
    }

    // Toggle Modal
    $(document).on('click', '.lqc-toggle-modal', function (e) {
        e.preventDefault();
        $('body').addClass('lqc-modal-open');
    });

    $(document).on('click', '.lqc-close-modal, #lqc-modal-overlay', function (e) {
        e.preventDefault();
        $('body').removeClass('lqc-modal-open');
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape' && $('body').hasClass('lqc-modal-open')) {
            $('body').removeClass('lqc-modal-open');
            $('#lqc-floating-basket').trigger('focus');
        }
    });

    // Go to Page (if popup disabled)
    $(document).on('click', '.lqc-go-to-page', function (e) {
        if (lqc_vars.quote_page_url) {
            window.location.href = lqc_vars.quote_page_url;
        }
    });

    // Remove Item
    $(document).on('click', '.lqc-remove', function (e) {
        e.preventDefault();
        var $item = $(this).closest('.lqc-item');
        var product_id = $item.data('id');

        $.post(lqc_vars.ajax_url, {
            action: 'lqc_remove_cart_item',
            nonce: lqc_vars.nonce,
            product_id: product_id
        }, function (response) {
            if (response.success) {
                $item.slideUp(function () { $(this).remove(); });
                updateCounter(response.data.count);
                if (response.data.count === 0) {
                    // Refresh to show empty message or handle via JS
                    location.reload();
                }
            }
        });
    });

    // Update Quantity or Variations
    $(document).on('change', '.lqc-qty, .lqc-change-variation', function () {
        var $input = $(this);
        var $item = $input.closest('.lqc-item');
        var cart_item_key = $item.data('id');
        var product_id = $item.data('product_id');
        var qty = $item.find('.lqc-qty').val();
        
        var variation_id = 0;
        var variations = {};

        // If the product is variable, gather all selected variations in this row
        if ($item.find('.lqc-change-variation').length) {
            var allSelected = true;
            $item.find('.lqc-change-variation').each(function() {
                var val = $(this).val();
                if (!val) {
                    allSelected = false; // missing a selection
                } else {
                    variations[$(this).data('attribute_name')] = val;
                }
            });

            if (!allSelected) return;
            // We need to resolve the new variation_id based on selected attributes. 
            // In a full implementation, WordPress does this via data attributes.
            // For LQC MVP, we will send the attributes back to the server and let WC figure it out
            // or we just trust the shift if we can't. Actually WooCommerce provides a way to find matching variation.
            // But we can just send product_id and variations to backend to resolve.
        }

        $.post(lqc_vars.ajax_url, {
            action: 'lqc_update_cart_item',
            nonce: lqc_vars.nonce,
            cart_item_key: cart_item_key,
            product_id: product_id,
            quantity: qty,
            variations: variations
        }, function (response) {
            if (response.success) {
                updateCounter(response.data.count);
                if (response.data.html) {
                    // Refresh the modal HTML so the new key and image are updated
                    refreshView(response.data.html);
                }
            } else {
                alert(response.data.message);
                refreshCartFragments();
            }
        }).fail(function () { alert('No se pudo confirmar la cantidad. Revisa tu lista.'); });
    });

    // Submit Form
    $(document).on('submit', '.lqc-contact-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $response = $form.find('.lqc-response');

        $btn.prop('disabled', true);
        $response.text('').removeClass('success error');

        $form.data('submitting', true);
        var data = $form.serialize();
        if (lqc_vars.pdf_enabled) {
            if (!$form.data('request-id')) {
                var bytes = crypto.getRandomValues(new Uint8Array(16));
                bytes[6] = (bytes[6] & 15) | 64;
                bytes[8] = (bytes[8] & 63) | 128;
                var hex = Array.from(bytes, function (n) { return n.toString(16).padStart(2, '0'); }).join('');
                $form.data('request-id', hex.replace(/(.{8})(.{4})(.{4})(.{4})(.{12})/, '$1-$2-$3-$4-$5'));
            }
            data += '&request_id=' + encodeURIComponent($form.data('request-id'));
        }
        data += '&action=lqc_submit_quote&nonce=' + encodeURIComponent(lqc_vars.nonce);
        $.post(lqc_vars.ajax_url, data).done(function (response) {
            if (!response.success) {
                $response.addClass('error').text(response.data.message);
                return;
            }
            var box = $('<div>', {class: 'lqc-result', role: 'status'});
            $('<p>').text(response.data.message).appendTo(box);
            if (response.data.pdf) {
                var binary = atob(response.data.pdf);
                var bytes = Uint8Array.from(binary, function (c) { return c.charCodeAt(0); });
                var url = URL.createObjectURL(new Blob([bytes], {type: 'application/pdf'}));
                $('<a>', {class: 'button lqc-download', href: url, download: response.data.folio + '.pdf'})
                    .text('Descargar cotización PDF').appendTo(box);
            }
            $('.lqc-modal-body, .lqc-cart-wrapper').empty().append(box);
            updateCounter(0);
        }).fail(function () {
            $response.addClass('error').text('Se interrumpió la conexión. Tus datos siguen aquí. Vuelve a intentarlo.');
        }).always(function () {
            $form.removeData('submitting');
            $btn.prop('disabled', false);
        });
    });
});
