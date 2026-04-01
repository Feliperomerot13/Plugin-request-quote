jQuery(document).ready(function ($) {

    // AJAX Fragment Refresh for caching
    function refreshCartFragments() {
        $.post(lqc_vars.ajax_url, {
            action: 'lqc_get_fragments'
        }, function(response) {
            if (response.success && response.data) {
                updateCounter(response.data.count);
                if (response.data.html) {
                    $('.lqc-modal-body').html(response.data.html);
                    $('.lqc-cart-wrapper').html(response.data.html);
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

        $btn.addClass('loading');

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
                    var $modalBody = $('.lqc-modal-body');
                    var $shortcodeWrapper = $('.lqc-cart-wrapper');
                    var updated = false;

                    if ($modalBody.length) {
                        $modalBody.html(response.data.html);
                        updated = true;
                    }

                    if ($shortcodeWrapper.length) {
                        $shortcodeWrapper.html(response.data.html);
                        updated = true;
                    }

                    if (!updated) {
                        console.warn('LQC: No cart container found (.lqc-modal-body or .lqc-cart-wrapper). Cart updated in background.');
                    }
                }

                // Optional: Open modal immediately?
                // $('body').addClass('lqc-modal-open');
            } else {
                alert(response.data.message);
            }
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
                    $('.lqc-modal-body').html(response.data.html);
                    $('.lqc-cart-wrapper').html(response.data.html);
                }
            }
        });
    });

    // Submit Form
    $(document).on('submit', '#lqc-quote-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $response = $form.find('.lqc-response');

        $btn.prop('disabled', true);
        $response.text('').removeClass('success error');

        var data = $form.serialize();
        data += '&action=lqc_submit_quote&nonce=' + lqc_vars.nonce;

        $.post(lqc_vars.ajax_url, data, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $response.addClass('success').text(response.data.message);
                $form.find('input, textarea').val('');
                $('.lqc-items-list').html('<p>' + response.data.message + '</p>');
                updateCounter(0);
                setTimeout(function () {
                    $('body').removeClass('lqc-modal-open');
                    // location.reload(); // Optional
                }, 3000);
            } else {
                $response.addClass('error').text(response.data.message);
            }
        });
    });

});
