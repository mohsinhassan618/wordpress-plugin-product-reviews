<?php
/**
 * Plugin Name: Simple Reviews
 * Description: A simple WordPress plugin that registers a custom post type for product reviews and provides REST API support.
 * Version: 1.0.0
 * Author: Mohsin Hassan
 */

if (!defined('ABSPATH')) {
    exit;
}



/**
 * Class Simple_Reviews
 *
 * Registers a custom post type for product reviews and provides:
 * - Shortcode `[simple_reviews]` to display recent reviews.
 * - REST API endpoints:
 *     POST /wp-json/mock-api/v1/sentiment/ - Analyze sentiment of provided text.
 *     GET  /wp-json/mock-api/v1/review-history/ - Get recent product reviews with sentiment.
 *
 * Main Methods:
 * - register_product_review_cpt(): Registers the 'product_review' post type.
 * - register_shortcodes(): Registers the `[simple_reviews]` shortcode.
 * - register_rest_routes(): Registers custom REST API routes.
 * - analyze_sentiment(): Handles sentiment analysis requests.
 * - get_review_history(): Returns recent reviews via REST.
 * - display_product_reviews(): Renders reviews for the shortcode.
 */
class Simple_Reviews {
    public function __construct() {
        add_action('init', [$this, 'register_product_review_cpt']);
	    add_action('init', [$this, 'register_shortcodes']);
	    add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

	public function register_shortcodes() {
		add_shortcode('simple_reviews', [$this, 'display_product_reviews']);
	}

 
    public function register_product_review_cpt() {
        register_post_type('product_review', [
            'labels'      => [
                'name'          => 'Product Reviews',
                'singular_name' => 'Product Review'
            ],
            'public'      => true,
            'supports'    => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
        ]);
    }

    public function register_rest_routes() {
	    // http://localhost:8080/wp-json/mock-api/v1/sentiment
        register_rest_route('mock-api/v1', '/sentiment/', [
            'methods'  => 'POST',
            'callback' => [$this, 'analyze_sentiment'],
            'permission_callback' => '__return_true',
        ]);

	    // http://localhost:8080/wp-json/mock-api/v1/review-history/
        register_rest_route('mock-api/v1', '/review-history/', [
            'methods'  => 'GET',
            'callback' => [$this, 'get_review_history'],
            'permission_callback' => '__return_true',
        ]);
    }


	/**
	 * Handles sentiment analysis requests from the REST API.
	 *
	 * @param WP_REST_Request $request The REST request containing the text to analyze.
	 * @return WP_Error|WP_REST_Response The sentiment analysis result or error.
	 */
    public function analyze_sentiment($request) {

        $params = $request->get_json_params();
        $text = isset($params['text']) ? sanitize_text_field($params['text']) : '';
        
        if (empty($text)) {
            return new WP_Error('empty_text', 'No text provided for analysis.', ['status' => 400]);
        }

        $sentiment_scores = ['positive' => 0.9, 'negative' => 0.2, 'neutral' => 0.5];
        $random_sentiment = array_rand($sentiment_scores);
        return rest_ensure_response(['sentiment' => $random_sentiment, 'score' => $sentiment_scores[$random_sentiment]]);
    }



	/**
	 * Returns recent product reviews with sentiment data via REST API.
	 *
	 * @return WP_REST_Response List of recent reviews with sentiment and score.
	 */
    public function get_review_history() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        
        $response = [];
        foreach ($reviews as $review) {
            $response[] = [
                'id'       => $review->ID,
                'title'    => $review->post_title,
                'sentiment'=> get_post_meta($review->ID, 'sentiment', true) ?? 'neutral',
                'score'    => get_post_meta($review->ID, 'sentiment_score', true) ?? 0.5,
            ];
        }

        return rest_ensure_response($response);
    }

    public function display_product_reviews() {
        $reviews = get_posts([
            'post_type'      => 'product_review',
            'posts_per_page' => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $output = '<style>
            .review-positive { color: green; font-weight: bold; }
            .review-negative { color: red; font-weight: bold; }
        </style>';

        $output .= '<ul>';
        foreach ($reviews as $review) {
            $sentiment = get_post_meta($review->ID, 'sentiment', true) ?? 'neutral';
            $class = ($sentiment === 'positive') ? 'review-positive' : (($sentiment === 'negative') ? 'review-negative' : '');
            $output .= "<li class='$class'>{$review->post_title} (Sentiment: $sentiment)</li>";
        }
        $output .= '</ul>';

        return $output;
    }
}

new Simple_Reviews();
