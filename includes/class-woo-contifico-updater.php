<?php
/**
 * Handles plugin updates from GitHub releases.
 *
 * @package Woo_Contifico\Includes
 */
class Woo_Contifico_Updater
{
    /**
     * Plugin file path.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Plugin basename.
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $slug;

    /**
     * GitHub repository owner.
     *
     * @var string
     */
    private $github_owner;

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private $github_repo;

    /**
     * GitHub branch used as fallback when no release exists.
     *
     * @var string
     */
    private $github_branch;

    /**
     * Optional GitHub personal access token.
     *
     * @var string
     */
    private $access_token;

    /**
     * Cached GitHub API response.
     *
     * @var array|null
     */
    private $api_response = NULL;

    /**
     * Plugin header data.
     *
     * @var array
     */
    private $plugin_data = [];

    /**
     * Constructor.
     *
     * @param string $plugin_file   Absolute path to the plugin main file.
     * @param string $github_owner  GitHub repository owner.
     * @param string $github_repo   GitHub repository name.
     * @param string $github_branch Repository branch.
     * @param string $access_token  Optional GitHub token.
     */
    public function __construct($plugin_file, $github_owner, $github_repo, $github_branch = 'main', $access_token = '')
    {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->slug            = dirname($this->plugin_basename);
        $this->github_owner    = $github_owner;
        $this->github_repo     = $github_repo;
        $this->github_branch   = $github_branch;
        $this->access_token    = $access_token;

        if ($this->slug === '.' || $this->slug === '\\') {
            $this->slug = $this->plugin_basename;
        }

        if ( ! function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $this->plugin_data = get_plugin_data($this->plugin_file, FALSE, FALSE);
    }

    /**
     * Register hooks required for the updater.
     */
    public function init()
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'maybe_rename_source_dir'], 10, 4);
        add_filter('http_request_args', [$this, 'authorize_http_request'], 10, 2);
    }

    /**
     * Inject authorization header for GitHub requests when a token is provided.
     *
     * @param array  $args Request arguments.
     * @param string $url  Request URL.
     *
     * @return array
     */
    public function authorize_http_request($args, $url)
    {
        if (empty($this->access_token)) {
            return $args;
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        $allowed_hosts = ['api.github.com', 'github.com', 'codeload.github.com', 'objects.githubusercontent.com'];

        if ($host && in_array($host, $allowed_hosts, TRUE)) {
            if ( ! isset($args['headers']) || ! is_array($args['headers'])) {
                $args['headers'] = [];
            }

            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        return $args;
    }

    /**
     * Checks GitHub for a new release and notifies WordPress if one exists.
     *
     * @param stdClass $transient Update transient.
     *
     * @return stdClass
     */
    public function check_for_update($transient)
    {
        if (empty($this->github_owner) || empty($this->github_repo)) {
            return $transient;
        }

        if (empty($transient->checked) || ! isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (is_wp_error($release) || empty($release['tag_name'])) {
            return $transient;
        }

        $remote_version = $this->normalize_version($release['tag_name']);
        if (empty($remote_version) || version_compare($remote_version, WOO_CONTIFICO_VERSION, '<=')) {
            return $transient;
        }

        $package = $this->get_package_url($release);
        if (empty($package)) {
            return $transient;
        }

        $update = (object) [
            'slug'        => $this->slug,
            'plugin'      => $this->plugin_basename,
            'new_version' => $remote_version,
            'url'         => ! empty($release['html_url']) ? $release['html_url'] : $this->get_plugin_header_value('PluginURI'),
            'package'     => $package,
        ];

        $requires_wp = $this->get_plugin_header_value('RequiresWP');
        if ( ! empty($requires_wp)) {
            $update->requires = $requires_wp;
        }

        $requires_php = $this->get_plugin_header_value('RequiresPHP');
        if ( ! empty($requires_php)) {
            $update->requires_php = $requires_php;
        }

        $transient->response[$this->plugin_basename] = $update;

        return $transient;
    }

    /**
     * Provides plugin information for the update details modal.
     *
     * @param false|object|array $result The result object or array. Default false.
     * @param string             $action The type of information being requested from the Plugin Install API.
     * @param object             $args   Plugin API arguments.
     *
     * @return mixed
     */
    public function plugins_api($result, $action, $args)
    {
        if ('plugin_information' !== $action || ! isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (is_wp_error($release) || empty($release['tag_name'])) {
            return $result;
        }

        $remote_version = $this->normalize_version($release['tag_name']);
        $sections = [];
        $description = $this->get_plugin_header_value('Description');
        $requires_wp = $this->get_plugin_header_value('RequiresWP');
        $requires_php = $this->get_plugin_header_value('RequiresPHP');

        if ( ! empty($release['body'])) {
            $sections['changelog'] = wpautop(wp_kses_post($release['body']));
        }

        if ( ! empty($description)) {
            $sections['description'] = wpautop(wp_kses_post($description));
        }

        if (empty($sections)) {
            $sections['description'] = esc_html__('Automatic updates powered by GitHub releases.', 'woo-contifico');
        }

        $info = (object) [
            'name'             => $this->get_plugin_header_value('Name'),
            'slug'             => $this->slug,
            'version'          => $remote_version,
            'author'           => $this->get_plugin_header_value('Author'),
            'author_profile'   => $this->get_plugin_header_value('AuthorURI'),
            'homepage'         => $this->get_plugin_header_value('PluginURI'),
            'download_link'    => $this->get_package_url($release),
            'sections'         => $sections,
            'short_description'=> ! empty($description) ? wp_strip_all_tags($description) : '',
        ];

        if ( ! empty($requires_wp)) {
            $info->requires = $requires_wp;
        }

        if ( ! empty($requires_php)) {
            $info->requires_php = $requires_php;
        }

        $tested_up_to = $this->get_plugin_header_value('Tested up to');
        if ( ! empty($tested_up_to)) {
            $info->tested = $tested_up_to;
        }

        if ( ! empty($release['published_at'])) {
            $timestamp = strtotime($release['published_at']);
            if ($timestamp) {
                $info->last_updated = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }

        return $info;
    }

    /**
     * Ensures the extracted plugin directory matches the expected slug.
     *
     * @param string      $source        The path to the source directory.
     * @param string      $remote_source Remote file source location.
     * @param WP_Upgrader $upgrader      Upgrader instance.
     * @param array       $hook_extra    Extra hook arguments.
     *
     * @return string
     */
    public function maybe_rename_source_dir($source, $remote_source, $upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }

        $destination = trailingslashit(dirname($source)) . $this->slug . '/';

        if ($source === $destination) {
            return $source;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem) {
            return $source;
        }

        if ($wp_filesystem->exists($destination)) {
            $wp_filesystem->delete($destination, TRUE);
        }

        if ($wp_filesystem->move($source, $destination, TRUE)) {
            return $destination;
        }

        return $source;
    }

    /**
     * Retrieve the latest release data from GitHub.
     *
     * @return array|WP_Error
     */
    private function get_latest_release()
    {
        if (empty($this->github_owner) || empty($this->github_repo)) {
            return new WP_Error('woo_contifico_updater_missing_repo', 'GitHub repository metadata is missing.');
        }

        if (NULL !== $this->api_response) {
            return $this->api_response;
        }

        $cache_key = 'woo_contifico_updater_' . md5($this->github_owner . '_' . $this->github_repo);
        $cached = get_site_transient($cache_key);
        if (FALSE !== $cached) {
            $this->api_response = $cached;
            return $this->api_response;
        }

        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->github_owner, $this->github_repo);
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => $this->github_repo . '-wp-updater',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== (int) $code) {
            return new WP_Error('woo_contifico_updater_http_error', sprintf('GitHub API error: %s', $code));
        }

        $body = json_decode(wp_remote_retrieve_body($response), TRUE);
        if (NULL === $body) {
            return new WP_Error('woo_contifico_updater_json_error', 'Unable to parse GitHub API response.');
        }

        $this->api_response = $body;
        set_site_transient($cache_key, $body, HOUR_IN_SECONDS * 6);

        return $this->api_response;
    }

    /**
     * Build the download package URL from the release payload.
     *
     * @param array $release GitHub release array.
     *
     * @return string
     */
    private function get_package_url($release)
    {
        if ( ! empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (isset($asset['browser_download_url']) && preg_match('/\.zip$/', $asset['browser_download_url'])) {
                    return $asset['browser_download_url'];
                }
            }
        }

        if ( ! empty($release['zipball_url'])) {
            return $release['zipball_url'];
        }

        if ( ! empty($release['tag_name'])) {
            return sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $this->github_owner, $this->github_repo, $release['tag_name']);
        }

        if ( ! empty($this->github_branch)) {
            return sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $this->github_owner, $this->github_repo, $this->github_branch);
        }

        return '';
    }

    /**
     * Normalize version strings received from GitHub.
     *
     * @param string $tag Version tag.
     *
     * @return string
     */
    private function normalize_version($tag)
    {
        $tag = trim($tag);
        if (strpos($tag, 'v') === 0) {
            $tag = substr($tag, 1);
        }

        return $tag;
    }

    /**
     * Safe accessor for plugin header values.
     *
     * @param string $key Header key.
     *
     * @return string
     */
    private function get_plugin_header_value($key)
    {
        if (empty($this->plugin_data) || ! is_array($this->plugin_data)) {
            return '';
        }

        if ( ! array_key_exists($key, $this->plugin_data)) {
            return '';
        }

        $value = $this->plugin_data[$key];

        if (is_array($value)) {
            return '';
        }

        return (string) $value;
    }
}
