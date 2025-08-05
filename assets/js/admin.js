jQuery(document).ready(function($) {
    // Add new rule
    $('.lr-add-rule').click(function() {
        var index = $('.lr-rule').length;
        var html = `
            <div class="lr-rule">
                <div class="lr-rule-grid">
                    <p>
                        <label>Slug</label>
                        <input type="text" name="lr_rules[${index}][slug]" value="" placeholder="e.g., meow" class="lr-slug-input">
                    </p>
                    <p>
                        <label>Type</label>
                        <select name="lr_rules[${index}][type]" class="lr-type-select">
                            <option value="category">Category</option>
                            <option value="tag">Tag</option>
                        </select>
                    </p>
                    <p class="lr-category-field">
                        <label>Category</label>
                        <select name="lr_rules[${index}][category]" class="lr-category-select">
                            ${lrAjax.categoryOptions}
                        </select>
                    </p>
                    <p class="lr-tag-field" style="display:none;">
                        <label>Tag</label>
                        <select name="lr_rules[${index}][tag]" class="lr-tag-select">
                            ${lrAjax.tagOptions}
                        </select>
                    </p>
                    <p>
                        <button type="button" class="button lr-remove-rule">Remove</button>
                    </p>
                </div>
                <p class="lr-redirect-flow disabled">No redirect set</p>
            </div>`;
        $('#lr-rules-container').append(html);
        $('.lr-empty-message').remove();
    });

    // Remove rule
    $(document).on('click', '.lr-remove-rule', function() {
        $(this).closest('.lr-rule').remove();
        if ($('.lr-rule').length === 0) {
            $('#lr-rules-container').append('<p class="lr-empty-message">No rules defined. Click "Add New Rule" to start.</p>');
        }
    });

    // Toggle category/tag fields based on type
    $(document).on('change', '.lr-type-select', function() {
        var rule = $(this).closest('.lr-rule');
        if ($(this).val() === 'category') {
            rule.find('.lr-category-field').show();
            rule.find('.lr-tag-field').hide();
        } else {
            rule.find('.lr-category-field').hide();
            rule.find('.lr-tag-field').show();
        }
        updateRedirectFlow(rule);
    });

    // Update redirect flow on slug, category, or tag change
    $(document).on('change input', '.lr-slug-input, .lr-category-select, .lr-tag-select', function() {
        var rule = $(this).closest('.lr-rule');
        updateRedirectFlow(rule);
    });

    // Function to update redirect flow text via AJAX
    function updateRedirectFlow(rule) {
        var slug = rule.find('.lr-slug-input').val();
        var type = rule.find('.lr-type-select').val();
        var term = type === 'category' ? rule.find('.lr-category-select').val() : rule.find('.lr-tag-select').val();
        var flowText = rule.find('.lr-redirect-flow');

        if (!slug || !term) {
            flowText.addClass('disabled').text('No redirect set');
            return;
        }

        var slugUrl = lrAjax.homeurl + slug;

        $.ajax({
            url: lrAjax.ajaxurl,
            method: 'POST',
            data: {
                action: 'lr_get_preview_url',
                type: type,
                term: term
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    flowText.removeClass('disabled').html('<a href="' + slugUrl + '">/' + slug + '</a> &rarr; <a href="' + response.data.url + '">' + response.data.url + '</a>');
                } else {
                    flowText.addClass('disabled').html('<a href="' + slugUrl + '">/' + slug + '</a> &rarr; (no posts found)');
                }
            },
            error: function() {
                flowText.addClass('disabled').html('<a href="' + slugUrl + '">/' + slug + '</a> &rarr; (error)');
            }
        });
    }
});
