<?php

/**
 * Class that makes it possible to use ArlimaAbstractListTemplateRenderer on ordinary
 * wordpress loops (while have_posts() => the_post() ...)
 *
 * @package Arlima
 * @since 2.0
 */
class Arlima_WPLoop extends Arlima_ListTemplateRenderer
{

    /**
     * @var array
     */
    private $exclude_posts = array();

    /**
     * @var string
     */
    private $filter_suffix;

    /**
     * @var int
     */
    private $list_width;

    /**
     * @var callable
     */
    private $header_callback = 'Arlima_WPLoop::defaultHeaderCallback';

    /**
     * @var array
     */
    private $default_article_props = array();

    /**
     * @var bool
     */
    private $doApplyFilters = true;

    /**
     * @param string $template_path - Optional path to directory where templates should exists (see readme.txt about how to add your own template paths from the theme)
     * @param null|string $template - Optional name of template file to be used (no extension)
     * @param int $list_width
     * @param string $filter_suffix
     */
    function __construct($template_path = null, $template = Arlima_TemplatePathResolver::DEFAULT_TMPL, $list_width=468, $filter_suffix='')
    {
        $list = new Arlima_List();
        $list->setOption('template', $template);
        $this->list_width = $list_width;
        $this->filter_suffix = $filter_suffix;
        parent::__construct($list, $template_path);
    }

    public function toggleApplyFilters($toggle)
    {
        $this->doApplyFilters = $toggle;
    }

    /**
     * @param array $exclude_posts
     */
    public function setExcludePosts($exclude_posts)
    {
        $this->exclude_posts = $exclude_posts;
    }

    /**
     * @return array
     */
    public function getExcludePosts()
    {
        return $this->exclude_posts;
    }

    /**
     * @param int $list_width
     */
    public function setListWidth($list_width)
    {
        $this->list_width = $list_width;
    }

    /**
     * @return int
     */
    public function getListWidth()
    {
        return $this->list_width;
    }

    /**
     * @param string $filter_suffix
     */
    public function setFilterSuffix($filter_suffix)
    {
        $this->filter_suffix = $filter_suffix;
    }

    /**
     * @return string
     */
    public function getFilterSuffix()
    {
        return $this->filter_suffix;
    }

    /**
     * @param int $article_counter
     * @param array $article
     * @param stdClass $post
     * @param Arlima_List $list
     * @return mixed
     */
    public static function defaultHeaderCallback($article_counter, $article, $post, $list) {
        return $article['html_title'];
    }

    /**
     * @param Closure $callback
     */
    function setHeaderCallback($callback)
    {
        $this->header_callback = $callback;
    }

    /**
     * @return bool
     */
    function havePosts()
    {
        return have_posts();
    }

    /**
     * @param bool $output
     * @return string
     */
    function renderList($output = true)
    {
        $article_counter = 0;
        $content = '';
        $current_global_post = $GLOBALS['post'];

        // Create template
        $this->default_template_name = $this->list->getOption('template');
        $this->default_template_obj = $this->loadTemplate($this->default_template_name);

        // Setup tmpl object creatorv
        $this->setup_wp_post_data = false; // prevent this class from overwriting the global post object

        if( $this->doApplyFilters ) {
            Arlima_FilterApplier::setFilterSuffix($this->filter_suffix);
            Arlima_FilterApplier::setArticleWidth($this->list_width);
            Arlima_FilterApplier::applyFilters($this);
        }

        $this->setupObjectCreator();

        while (have_posts()) {
            if ( $this->getOffset() > $article_counter ) {
                $article_counter++;
                continue;
            }

            the_post();
            global $post;

            $template_data = apply_filters('arlima_wp_lopp_article', $this->extractTemplateData($post, $article_counter));

            if( $template_data && !in_array($post->ID, $this->exclude_posts) ) {

                list($article_counter, $article_content) = $this->outputArticle(
                    $template_data,
                    $article_counter
                );

                if ( $output ) {
                    echo $article_content;

                } else {
                    $content .= $article_content;
                }
            }

            if ( $article_counter >= 50 || ($this->getLimit() > -1 && $this->getLimit() <= $article_counter) ) {
                break;
            }
        }

        // Reset
        $GLOBALS['post'] = $current_global_post;

        if( $this->doApplyFilters ) {
            Arlima_FilterApplier::setFilterSuffix('');
        }

        return $content;
    }

    /**
     * @param WP_Post $post
     * @return array
     */
    protected function extractTemplateData($post, $article_counter)
    {
        $date = strtotime($post->post_date);
        $article = array_merge(array(
            'post' => $post->ID,
            'options' => array(
                'hideRelated' => false
            ),
            'title' => apply_filters('the_title', $post->post_title, 'arlima-list'),
            'html_title' => '<h2>' . $post->post_title . '</h2>',
            'url' => get_permalink($post->ID),
            'content' => '',
            'size' => 24,
            'created' => $date,
            'published' => $date
        ), $this->default_article_props);

        $article = Arlima_ListFactory::createArticleDataArray($article);
        $article['title_html'] = call_user_func($this->header_callback, $article_counter, $article, $post, $this->list);
        $article['content'] = apply_filters('the_content', get_the_content(), 'arlima-list');
        $article['content'] = apply_filters('the_content', get_the_content());

        if( $img = get_post_thumbnail_id($post->ID) ) {
            $article['image'] = array(
                'attachment' => $img,
                'alignment' => '',
                'size' => 'full',
                'url' => wp_get_attachment_url($img)
            );
        }

        return apply_filters('arlima_wp_loop_article', $article, $post);
    }

    /**
     * @param $arr
     */
    public function setDefaultArticleProperties($arr)
    {
        $this->default_article_props = $arr;
    }
}