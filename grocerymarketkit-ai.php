<?php
/*
Plugin Name: Grocery Market Kit - AI
Description: A plugin to select ingredients and generate AI recipes using AI.
Version: 1.0
Author: Jessica K. Murray
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Register Custom Post Type for AI Recipes
function gmk_register_ai_recipe_post_type()
{
    $labels = array(
        'name' => 'AI Recipes',
        'singular_name' => 'AI Recipe',
        'add_new' => 'Add New AI Recipe',
        'add_new_item' => 'Add New AI Recipe',
        'edit_item' => 'Edit AI Recipe',
        'new_item' => 'New AI Recipe',
        'view_item' => 'View AI Recipe',
        'search_items' => 'Search AI Recipes',
        'not_found' => 'No AI recipes found',
        'not_found_in_trash' => 'No AI recipes found in trash',
        'all_items' => 'All AI Recipes',
        'menu_name' => 'AI Recipes',
        'name_admin_bar' => 'AI Recipe',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'ai-recipes'),
        'supports' => array('title', 'editor', 'thumbnail'),
    );

    register_post_type('ai_recipe', $args);
}
add_action('init', 'gmk_register_ai_recipe_post_type');

// Register Custom Taxonomy for Ingredients
function gmk_register_ingredient_taxonomy()
{
    $labels = array(
        'name' => 'Ingredients',
        'singular_name' => 'Ingredient',
        'search_items' => 'Search Ingredients',
        'all_items' => 'All Ingredients',
        'parent_item' => 'Parent Ingredient',
        'parent_item_colon' => 'Parent Ingredient:',
        'edit_item' => 'Edit Ingredient',
        'update_item' => 'Update Ingredient',
        'add_new_item' => 'Add New Ingredient',
        'new_item_name' => 'New Ingredient Name',
        'menu_name' => 'Ingredients',
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'public' => true,
        'rewrite' => array('slug' => 'ingredients'),
    );

    register_taxonomy('ingredient', array('ai_recipe'), $args);
}
add_action('init', 'gmk_register_ingredient_taxonomy');

// Shortcode to Display Ingredient Selection Form
function gmk_ingredient_selection_form()
{
    $ingredients = get_terms(array(
        'taxonomy' => 'ingredient',
        'hide_empty' => false,
    ));

    ob_start();
    ?>
    <style>
        .gmk-ingredient-columns {
            column-count: 3;
            column-gap: 20px;
        }
        .gmk-ingredient-columns ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .gmk-ingredient-columns li {
            break-inside: avoid;
            padding: 5px 0;
        }
    </style>
    <form id="gmk-ingredient-form">
        <h3>Select Ingredients:</h3>
        <div class="gmk-ingredient-columns">
            <ul>
                <?php foreach ($ingredients as $ingredient) : ?>
                    <li>
                        <label>
                            <input type="checkbox" name="ingredients[]" value="<?php echo esc_attr($ingredient->name); ?>">
                            <?php echo esc_html($ingredient->name); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <h3>Other Ingredients:</h3>
        <input type="text" name="other_ingredients[]" placeholder="Other Ingredient 1">
        <input type="text" name="other_ingredients[]" placeholder="Other Ingredient 2">
        <input type="text" name="other_ingredients[]" placeholder="Other Ingredient 3">
        <button type="submit">Generate AI Recipes</button>
    </form>
    <div id="gmk-ai-recipes"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('gmk_ingredient_selection', 'gmk_ingredient_selection_form');

// Handle AJAX Request to Generate AI Recipes
function gmk_generate_ai_recipes()
{
    if (!isset($_POST['form_data'])) {
        wp_send_json_error('No ingredients selected.');
    }

    parse_str($_POST['form_data'], $form_data);

    $selected_ingredients = isset($form_data['ingredients']) ? $form_data['ingredients'] : [];
    $other_ingredients = isset($form_data['other_ingredients']) ? $form_data['other_ingredients'] : [];

    $ingredients = array_merge($selected_ingredients, $other_ingredients);

    // Create the prompt for OpenAI
    $prompt = "Give me easy recipes for a family of four using some or all of the selected ingredients which can be cooked in only one stovetop pot or pan for $5 or less per serving, using only SNAP eligible food items from https://schnucks.com/shop - include the itemized pricing for each ingredient and keep it at a 3rd grade reading level. Mark the ingredients not selected as ingredients needed. Ingredients: " . implode(", ", $ingredients);

    // Get the API key from the settings
    $api_key = get_option('gmk_openai_api_key');
    if (!$api_key) {
        wp_send_json_error('API key not set. Please go to the settings page to set your OpenAI API key.');
    }

    // Call OpenAI API
    $response = gmk_call_openai_api($prompt, $api_key);

    if (is_wp_error($response)) {
        error_log('OpenAI API Error: ' . $response->get_error_message());
        wp_send_json_error('Error generating AI recipes: ' . $response->get_error_message());
    }

    $recipes = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($recipes['choices'][0]['message']['content'])) {
        wp_send_json_success(nl2br(esc_html($recipes['choices'][0]['message']['content'])));
    } else {
        error_log('Failed to generate AI recipes. API Response: ' . wp_remote_retrieve_body($response));
        wp_send_json_error('Failed to generate AI recipes.');
    }
}
add_action('wp_ajax_gmk_generate_ai_recipes', 'gmk_generate_ai_recipes');
add_action('wp_ajax_nopriv_gmk_generate_ai_recipes', 'gmk_generate_ai_recipes');

// Function to Call OpenAI API
function gmk_call_openai_api($prompt, $api_key)
{
    $endpoint = 'https://api.openai.com/v1/chat/completions';

    $body = array(
        'model' => 'gpt-4',
        'messages' => array(
            array('role' => 'user', 'content' => $prompt)
        ),
        'max_tokens' => 500,
    );

    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($body),
        'timeout' => 60, // Increase timeout to 60 seconds
    ));

    if (is_wp_error($response)) {
        error_log('OpenAI API Request Error: ' . $response->get_error_message());
    }

    return $response;
}

// Enqueue Scripts
function gmk_enqueue_scripts()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('gmk-ajax-script', plugins_url('/js/gmk-ajax.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('gmk-ajax-script', 'gmk_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'gmk_enqueue_scripts');

// Add Settings Page
function gmk_add_settings_page()
{
    add_options_page(
        'Grocery Market Kit - AI Settings',
        'Grocery Market Kit - AI',
        'manage_options',
        'gmk-ai-settings',
        'gmk_render_settings_page'
    );
}
add_action('admin_menu', 'gmk_add_settings_page');

// Render Settings Page
function gmk_render_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Grocery Market Kit - AI Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gmk-ai-settings-group');
            do_settings_sections('gmk-ai-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenAI API Key</th>
                    <td><input type="text" name="gmk_openai_api_key" value="<?php echo esc_attr(get_option('gmk_openai_api_key')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register Settings
function gmk_register_settings()
{
    register_setting('gmk-ai-settings-group', 'gmk_openai_api_key');
}
add_action('admin_init', 'gmk_register_settings');
