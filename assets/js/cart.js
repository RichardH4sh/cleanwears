$(function () {
  'use strict';

  function csrfToken() {
    return $('#csrf-token').val();
  }

  function postCart(action, data) {
    return $.ajax({
      url: 'cart-action.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify($.extend({ action: action, csrf_token: csrfToken() }, data)),
      dataType: 'json',
    });
  }

  function updateCartCount(count) {
    $('#cart-count').text(count);
  }

  // --- Product page: Add to cart ---
  $('#add-to-cart-form').on('submit', function (e) {
    e.preventDefault();
    var $form = $(this);
    var $msg = $form.find('.add-to-cart-message');
    var $btn = $form.find('.add-to-cart-btn');

    var payload = {
      product_id: parseInt($form.data('product-id'), 10),
      quantity: parseInt($form.find('[name="quantity"]').val(), 10) || 1,
      variant: $form.find('[name="variant"]:checked').val() || null,
    };

    $btn.prop('disabled', true);
    $msg.removeClass('is-error').text('');

    postCart('add', payload)
      .done(function (res) {
        if (res.ok) {
          updateCartCount(res.count);
          $msg.text('Added to cart.');
        } else {
          $msg.addClass('is-error').text(res.error || 'Could not add to cart.');
        }
      })
      .fail(function (xhr) {
        var res = xhr.responseJSON;
        $msg.addClass('is-error').text((res && res.error) || 'Please log in to add items to your cart.');
        if (xhr.status === 401) {
          setTimeout(function () {
            window.location.href = 'login.php?return=' + encodeURIComponent(window.location.pathname + window.location.search);
          }, 900);
        }
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  // --- Cart page: quantity change ---
  var qtyTimer = null;
  $('#cart-rows').on('change', '.cart-qty-input', function () {
    var $input = $(this);
    var cartId = $input.data('cart-id');
    var quantity = parseInt($input.val(), 10) || 1;

    clearTimeout(qtyTimer);
    qtyTimer = setTimeout(function () {
      postCart('update', { cart_id: cartId, quantity: quantity })
        .done(function (res) {
          if (res.ok) {
            renderCart(res);
          } else {
            alert(res.error || 'Could not update quantity.');
          }
        });
    }, 300);
  });

  // --- Cart page: remove item ---
  $('#cart-rows').on('click', '.cart-remove-btn', function () {
    var cartId = $(this).data('cart-id');
    postCart('remove', { cart_id: cartId }).done(function (res) {
      if (res.ok) {
        renderCart(res);
      }
    });
  });

  function renderCart(res) {
    updateCartCount(res.count);
    $('#cart-subtotal').text(res.subtotal_formatted);

    if (!res.items.length) {
      $('#cart-content').hide();
      $('#cart-empty-state').show();
      return;
    }

    var $rows = $('#cart-rows');
    $rows.empty();
    res.items.forEach(function (item) {
      var variantHtml = item.variant ? '<div class="cart-variant">Size: ' + escapeHtml(item.variant) + '</div>' : '';
      var row = $(
        '<tr data-cart-id="' + item.cart_id + '">' +
          '<td><img class="thumb" src="' + item.image_url + '" alt=""></td>' +
          '<td>' + escapeHtml(item.name) + variantHtml + '</td>' +
          '<td class="cart-price">$' + Number(item.price).toFixed(2) + '</td>' +
          '<td><input type="number" class="cart-qty-input" min="1" max="' + item.stock_qty + '" value="' + item.quantity + '" data-cart-id="' + item.cart_id + '"></td>' +
          '<td class="cart-line-total">$' + Number(item.line_total).toFixed(2) + '</td>' +
          '<td><button type="button" class="link-danger cart-remove-btn" data-cart-id="' + item.cart_id + '">Remove</button></td>' +
        '</tr>'
      );
      $rows.append(row);
    });
  }

  function escapeHtml(str) {
    return $('<div>').text(str).html();
  }

  // --- Product gallery thumbnail swap ---
  $('.gallery-thumb').on('click', function () {
    $('#main-image').attr('src', $(this).data('full'));
  });
});
