<?php
/**
 * Template Name: BookRec Share Card
 * 
 * Template for displaying book recommendation share cards with Open Graph meta tags
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get book parameters from URL
$book_title = isset($_GET['title']) ? sanitize_text_field(urldecode($_GET['title'])) : '';
$book_author = isset($_GET['author']) ? sanitize_text_field(urldecode($_GET['author'])) : '';
$book_cover = isset($_GET['cover']) ? esc_url_raw(urldecode($_GET['cover'])) : '';
$book_description = isset($_GET['desc']) ? sanitize_text_field(urldecode($_GET['desc'])) : '';
$book_isbn = isset($_GET['isbn']) ? sanitize_text_field(urldecode($_GET['isbn'])) : '';

// Generate Amazon affiliate link if we have title/author
$amazon_tag = get_option('bookrec_amazon_tag', 'gooderead-20');
$amazon_link = '';
if (!empty($book_title) && !empty($book_author)) {
    $search_term = urlencode($book_title . ' ' . $book_author);
    $amazon_link = "https://www.amazon.com/s?k={$search_term}&tag={$amazon_tag}";
}

// Get current site info
$site_name = get_bloginfo('name');
$site_url = home_url();
$page_url = home_url(add_query_arg([], $_SERVER['REQUEST_URI']));

// Default image if cover not provided
$default_cover = BOOKREC_PLUGIN_URL . 'assets/images/default-book-cover.jpg';
$share_image = !empty($book_cover) ? $book_cover : $default_cover;

// Construct share title and description
$share_title = !empty($book_title) ? esc_html($book_title) : esc_html__('Book Recommendation', 'goodereader-bookrec');
if (!empty($book_author)) {
    $share_title .= ' ' . esc_html__('by', 'goodereader-bookrec') . ' ' . esc_html($book_author);
}
$share_title .= ' | ' . $site_name;

$share_description = !empty($book_description) 
    ? wp_trim_words($book_description, 30, '...') 
    : esc_html__('Check out this book recommendation from Good E-Reader!', 'goodereader-bookrec');

// Add Open Graph meta tags to head
add_action('wp_head', function() use ($share_title, $share_description, $share_image, $page_url, $site_name, $amazon_link) {
    ?>
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="book" />
    <meta property="og:url" content="<?php echo esc_url($page_url); ?>" />
    <meta property="og:title" content="<?php echo esc_attr($share_title); ?>" />
    <meta property="og:description" content="<?php echo esc_attr($share_description); ?>" />
    <meta property="og:image" content="<?php echo esc_url($share_image); ?>" />
    <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>" />
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:url" content="<?php echo esc_url($page_url); ?>" />
    <meta name="twitter:title" content="<?php echo esc_attr($share_title); ?>" />
    <meta name="twitter:description" content="<?php echo esc_attr($share_description); ?>" />
    <meta name="twitter:image" content="<?php echo esc_url($share_image); ?>" />
    
    <?php if (!empty($amazon_link)): ?>
    <!-- Book Specific -->
    <meta property="book:author" content="<?php echo esc_attr($book_author); ?>" />
    <?php if (!empty($book_isbn)): ?>
    <meta property="book:isbn" content="<?php echo esc_attr($book_isbn); ?>" />
    <?php endif; ?>
    <?php endif; ?>
    <?php
});

get_header();
?>

<div class="bookrec-share-container">
    <div class="bookrec-share-card">
        <?php if (!empty($book_title)): ?>
            <div class="bookrec-share-recommendation">
                <div class="bookrec-share-book">
                    <?php if (!empty($book_cover)): ?>
                        <div class="bookrec-share-cover">
                            <img src="<?php echo esc_url($book_cover); ?>" alt="<?php echo esc_attr($book_title); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="bookrec-share-details">
                        <h1 class="bookrec-share-title"><?php echo esc_html($book_title); ?></h1>
                        
                        <?php if (!empty($book_author)): ?>
                            <h2 class="bookrec-share-author"><?php echo esc_html__('by', 'goodereader-bookrec'); ?> <?php echo esc_html($book_author); ?></h2>
                        <?php endif; ?>
                        
                        <?php if (!empty($book_description)): ?>
                            <div class="bookrec-share-description">
                                <p><?php echo esc_html($book_description); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($amazon_link)): ?>
                            <div class="bookrec-share-action">
                                <a href="<?php echo esc_url($amazon_link); ?>" class="bookrec-amazon-button" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html__('View on Amazon', 'goodereader-bookrec'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bookrec-share-error">
                <h1><?php echo esc_html__('Book recommendation not found', 'goodereader-bookrec'); ?></h1>
                <p><?php echo esc_html__('The requested book recommendation could not be found.', 'goodereader-bookrec'); ?></p>
                <p><a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_html__('Return to homepage', 'goodereader-bookrec'); ?></a></p>
            </div>
        <?php endif; ?>
        
        <div class="bookrec-share-footer">
            <p><?php echo esc_html__('Recommended by', 'goodereader-bookrec'); ?> <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
            <p><?php echo esc_html__('Find more book recommendations at', 'goodereader-bookrec'); ?> <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_url); ?></a></p>
        </div>
    </div>
</div>

<style>
    .bookrec-share-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .bookrec-share-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        padding: 2rem;
    }
    
    .bookrec-share-book {
        display: flex;
        flex-direction: column;
    }
    
    @media (min-width: 768px) {
        .bookrec-share-book {
            flex-direction: row;
            gap: 2rem;
        }
    }
    
    .bookrec-share-cover {
        flex-shrink: 0;
        width: 100%;
        max-width: 200px;
        margin-bottom: 1.5rem;
    }
    
    @media (min-width: 768px) {
        .bookrec-share-cover {
            margin-bottom: 0;
        }
    }
    
    .bookrec-share-cover img {
        width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .bookrec-share-details {
        flex: 1;
    }
    
    .bookrec-share-title {
        font-size: 2rem;
        margin-bottom: 0.5rem;
        line-height: 1.2;
    }
    
    .bookrec-share-author {
        font-size: 1.2rem;
        color: #555;
        margin-bottom: 1rem;
    }
    
    .bookrec-share-description {
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }
    
    .bookrec-amazon-button {
        display: inline-block;
        background-color: #000;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    
    .bookrec-amazon-button:hover {
        background-color: #333;
        color: #fff;
    }
    
    .bookrec-share-footer {
        margin-top: 3rem;
        padding-top: 1.5rem;
        border-top: 1px solid #eee;
        font-size: 0.9rem;
        color: #666;
    }
    
    .bookrec-share-error {
        text-align: center;
        padding: 2rem 0;
    }
</style>

<?php
get_footer();