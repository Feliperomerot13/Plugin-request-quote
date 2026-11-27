jQuery(document).ready(function ($) {


    // BeTheme product sheets put the quote shortcode below the delivery copy.
    // Move the actual controls so the keyboard order follows the desktop layout.
    var desktopSheet = window.matchMedia('(min-width: 960px)');
    $('.mfn-single-product-tmpl-builder .sticky-desktop > .mcb-wrap-inner').each(function () {
        var column = $(this);
        var title = column.children('.column_product_title').first();
        var actions = column.children('.column_plain_text').filter(function () {
            return $(this).find('.lqc-add-to-quote').length > 0;
        }).first();
        if (!title.length || !actions.length) return;

        var originalSlot = document.createComment('Luma product actions');
        actions[0].before(originalSlot);
        var features = column.find('.woocommerce-product-details__short-description > ul').first();
        var disclosure = null;
        if (features.children('li').length >= 3) {
            disclosure = $('<details>', {class: 'lqc-product-features'});
            disclosure.append($('<summary>').text('Ver características'));
        }

        var panel = column.parent()[0];
        var header = document.querySelector('.mfn-header-tmpl-fixed');
        function measurePanel() {
            if (!desktopSheet.matches) return;
            var clearance = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
            panel.style.setProperty('--lqc-header-bottom', Math.ceil(clearance) + 'px');
            panel.style.setProperty('--lqc-panel-height', Math.ceil(panel.getBoundingClientRect().height) + 'px');
        }
        if (window.ResizeObserver) {
            var observer = new ResizeObserver(measurePanel);
            observer.observe(panel);
            if (header) observer.observe(header);
        }
        function layoutSheet() {
            if (desktopSheet.matches) {
                panel.classList.add('lqc-product-panel');
                actions.addClass('lqc-product-actions').insertAfter(title);
                if (disclosure && !disclosure[0].isConnected) {
                    features.before(disclosure);
                    disclosure.append(features);
                }
            } else {
                panel.classList.remove('lqc-product-panel');
                originalSlot.after(actions[0]);
                actions.removeClass('lqc-product-actions');
                if (disclosure && disclosure[0].isConnected) {
                    disclosure.before(features).detach();
                }
            }
        }
        layoutSheet();
        measurePanel();
        desktopSheet.addEventListener('change', layoutSheet);
    });

    function showStep(container, contact) {
        container.find('.lqc-items-list, .lqc-continue').prop('hidden', contact);
        container.find('.lqc-contact-form').prop('hidden', !contact);
        container.find('.lqc-steps li').removeAttr('aria-current').eq(contact ? 1 : 0).attr('aria-current', 'step');
    }

    $(document).on('click', '.lqc-continue, .lqc-back', function () {
        var container = $(this).closest('.lqc-modal-body, .lqc-cart-wrapper');
        var contact = $(this).hasClass('lqc-continue');
        if (contact) {
            var quantities = {};
            var inputs = container.find('.lqc-qty');
            for (var input of inputs.get()) {
                if (!input.value || !input.reportValidity()) {
                    input.focus();
                    return;
                }
                quantities[$(input).closest('.lqc-item').attr('data-id')] = input.value;
            }
            container.find('[name="lqc_quantities"]').val(JSON.stringify(quantities));
        }
        showStep(container, contact);
        container.find(contact ? '.lqc-contact-form input:visible' : '.lqc-continue').first().trigger('focus');
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
                var control = next.find('[name]').filter(function () { return this.name === field.name; });
                if (control.is(':checkbox')) control.prop('checked', true);
                else control.val(field.value);
            });
            showStep(container, inContact);
        });
    }

    function resetActions(buttons) {
        buttons.filter('.lqc-selected').each(function () {
            var button = $(this);
            button.find('.lqc-action-label').text(button.data('add-label'));
            button.removeClass('lqc-selected');
        });
    }

    $(document).on('change', '.lqc-shortcode-variation-select, form.cart [name^="attribute_"], form.cart [name="quantity"]', function () {
        resetActions($(this).closest('.product, .lqc-shortcode-container, form.cart').find('.lqc-add-to-quote'));
    });

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
        if ($btn.hasClass('lqc-selected')) {
            if ($('#lqc-modal').length) openModal(this);
            else if (lqc_vars.quote_page_url) window.location.assign(lqc_vars.quote_page_url);
            return;
        }
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

        $btn.addClass('loading').attr('aria-busy', 'true').prop('disabled', true);

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
                $('.lqc-announcement').text('Producto añadido a tu cotización.');
                if ($('#lqc-modal').length || lqc_vars.quote_page_url) {
                    var label = $btn.find('.lqc-action-label');
                    if (!label.length) {
                        label = $('<span>', {class:'lqc-action-label'}).text($btn.text().trim());
                        $btn.empty().append(label);
                    }
                    $btn.data('add-label', label.text()).addClass('lqc-selected');
                    label.text('Ver mi cotización');
                }
                updateCounter(response.data.count);

                // Update Modal Content with fresh HTML
                if (response.data.html) {
                    refreshView(response.data.html);
                }

            } else {
                alert(response.data.message);
            }
        }).fail(function () {
            alert('No se pudo confirmar la selección. Revisa tu lista antes de intentarlo otra vez.');
        }).always(function () {
            $btn.removeClass('loading').removeAttr('aria-busy').prop('disabled', false);
        });
    });

    // Update Counter
    function updateCounter(count) {
        $('.lqc-count').text(count);
        if (Number(count) === 0) resetActions($('.lqc-selected'));
    }

    var opener;
    function closeModal() {
        $('body').removeClass('lqc-modal-open');
        if (opener && opener.isConnected) opener.focus();
    }

    function openModal(trigger) {
        opener = trigger;
        $('body').addClass('lqc-modal-open');
        $('#lqc-modal .lqc-close-modal').trigger('focus');
    }

    $(document).on('click', '.lqc-toggle-modal', function (e) {
        e.preventDefault();
        openModal(this);
    });

    $(document).on('click', '.lqc-close-modal, #lqc-modal-overlay', function (e) {
        e.preventDefault();
        closeModal();
    });

    $(document).on('keydown', function (event) {
        if (!$('body').hasClass('lqc-modal-open')) return;
        if (event.key === 'Escape') closeModal();
        if (event.key !== 'Tab') return;
        var controls = $('#lqc-modal').find('a[href], button:enabled, input:enabled, textarea:enabled, select:enabled').filter(':visible');
        var first = controls.get(0), last = controls.get(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
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
                var parentId = String(product_id).split('_')[0];
                resetActions($('.lqc-selected').filter(function () {
                    return String($(this).data('product_id')) === parentId;
                }));
                if (response.data.html) refreshView(response.data.html);
                updateCounter(response.data.count);

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

        var submitLabel = $btn.text();
        $btn.attr('aria-busy', 'true').prop('disabled', true).text(lqc_vars.pdf_enabled ? 'Generando cotización…' : 'Enviando solicitud…');
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
            $('<h3>').text(response.data.pdf ? 'Tu cotización está lista' : 'Solicitud recibida').appendTo(box);
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
            $btn.removeAttr('aria-busy').prop('disabled', false).text(submitLabel);
        });
    });
});
