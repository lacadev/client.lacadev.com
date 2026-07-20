<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * post-source-query.php — bản tham chiếu dùng chung cho mọi block hiển thị
 * nhiều bài viết/CPT theo 2 chế độ 'auto'/'manual'.
 *
 * QUAN TRỌNG: KHÔNG require file này trực tiếp qua '../utils/post-source-query.php'.
 * Cơ chế Block Marketplace sync chỉ đóng gói ĐÚNG 1 thư mục block khi đẩy sang site
 * khách — không kéo theo utils/. Mỗi block cần dùng hàm này phải COPY nguyên file
 * này vào thư mục của chính nó (giống icons.php) rồi require_once __DIR__ . '/post-source-query.php'.
 * Xem RULE_BLOCK_GUTENBERG.md.
 *
 * Cùng contract thuộc tính với utils/post-source-controls.js:
 *   mode, postType, taxonomy, selectedTerms, postsCount, orderBy, order, selectedPosts.
 */

if ( ! function_exists( 'lcdc_build_post_source_query' ) ) {
    /**
     * @param array  $attributes        Block attributes ($attributes trong render.php).
     * @param string $default_post_type Post type mặc định khi attribute rỗng.
     * @param int    $default_count     Số bài mặc định (mode auto) khi attribute rỗng.
     * @return array WP_Query args.
     */
    function lcdc_build_post_source_query( array $attributes, string $default_post_type = 'post', int $default_count = 6 ): array {
        $post_type      = sanitize_key( $attributes['postType'] ?? $default_post_type ) ?: $default_post_type;
        $taxonomy       = sanitize_key( $attributes['taxonomy'] ?? '' );
        $selected_terms = array_map( 'absint', (array) ( $attributes['selectedTerms'] ?? [] ) );
        $mode           = $attributes['mode'] ?? 'auto';
        $order_by       = sanitize_key( $attributes['orderBy'] ?? 'date' );
        $order          = strtoupper( $attributes['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
        $posts_count    = max( 1, min( 50, intval( $attributes['postsCount'] ?? $default_count ) ) );
        $selected_posts = array_map( 'absint', (array) ( $attributes['selectedPosts'] ?? [] ) );

        $safe_orderby = in_array( $order_by, [ 'date', 'title', 'menu_order', 'comment_count', 'modified', 'rand' ], true )
            ? $order_by : 'date';

        if ( $mode === 'manual' && ! empty( $selected_posts ) ) {
            return [
                'post_type'           => $post_type,
                'post__in'            => $selected_posts,
                'orderby'             => 'post__in',
                'posts_per_page'      => count( $selected_posts ),
                'post_status'         => 'publish',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ];
        }

        $query_args = [
            'post_type'           => $post_type,
            'posts_per_page'      => $posts_count,
            'post_status'         => 'publish',
            'orderby'             => $safe_orderby,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ];

        // orderby=rand không nhận tham số order kèm theo.
        if ( $safe_orderby !== 'rand' ) {
            $query_args['order'] = $order;
        }

        if ( $taxonomy && ! empty( $selected_terms ) ) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $selected_terms,
                ],
            ];
        }

        return $query_args;
    }
}
