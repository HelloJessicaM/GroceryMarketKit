jQuery(document).ready(function($) {
    $('#gmk-ingredient-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();

        $.ajax({
            type: 'POST',
            url: gmk_ajax.ajax_url,
            data: {
                action: 'gmk_generate_ai_recipes',
                form_data: formData
            },
            success: function(response) {
                if (response.success) {
                    $('#gmk-ai-recipes').html(response.data);
                } else {
                    $('#gmk-ai-recipes').html('<p>' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $('#gmk-ai-recipes').html('<p>Error generating AI recipes: ' + error + '</p>');
            }
        });
    });
});
