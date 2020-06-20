jQuery(document).ready(function () {

  jQuery('#fetch_data').click(function () {
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: { action: "metaboxapi_action" },
      success: function (response) {
        alert(response.message);
      }
    })
  });

  jQuery('#_category_field').change(function () {
    var category = jQuery(this).val();
    jQuery('.shortcode_preview').html('');
    jQuery.ajax({
      type: "post",
      dataType: "json",
      url: ajaxurl,
      data: { action: "metaboxapi_products_action", 'category': category },
      success: function (response) {
        if (response.type == "success") {
          jQuery('#_product_field').html('<option value="">Select Product</option');
          jQuery.each(response.message, function (key, entry) {
            jQuery('#_product_field').append('<option value="' + entry.product_id + '">' + entry.product_name + '</option>');
          })
        }
        else {
          alert(response.message);
        }
      }
    })
  });

  jQuery('#generateCode').click(function(){
    var shortcode='<code>[CTA id="{PRODUCT}"]</code>';
    var product = jQuery('#_product_field').val();
    if(product!=''){
      var res=shortcode.replace('{PRODUCT}', product);
      jQuery('.shortcode_preview').html(res);
    }else{
      alert('Please select the product.');
    }
  });

});