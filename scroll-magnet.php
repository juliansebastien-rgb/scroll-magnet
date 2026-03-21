<?php
/**
 * Plugin Name: Scroll Magnet
 * Plugin URI: https://github.com/juliansebastien-rgb/scroll-magnet
 * Description: Behavior-based conversion CTAs triggered by visitor scroll depth.
 * Version: 0.2.0
 * Author: Le Labo d'Azertaf
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: scroll-magnet
 * Update URI: https://github.com/juliansebastien-rgb/scroll-magnet
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Scroll_Magnet {
    private const VERSION = '0.2.0';
    private const OPTION_KEY = 'scroll_magnet_settings';
    private const STATS_OPTION_KEY = 'scroll_magnet_stats';
    private const TRANSIENT_PREFIX = 'scroll_magnet_';
    private const GITHUB_REPOSITORY = 'juliansebastien-rgb/scroll-magnet';
    private const GITHUB_API_BASE = 'https://api.github.com/repos/juliansebastien-rgb/scroll-magnet';
    private const GITHUB_REPOSITORY_URL = 'https://github.com/juliansebastien-rgb/scroll-magnet';
    private const UPDATE_CACHE_TTL = HOUR_IN_SECONDS;

    public function boot(): void {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_scroll_magnet_track', [$this, 'handle_track_event']);
        add_action('wp_ajax_nopriv_scroll_magnet_track', [$this, 'handle_track_event']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_github_update']);
        add_filter('plugins_api', [$this, 'filter_plugin_information'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'normalize_github_update_source'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'clear_update_cache'], 10, 2);
    }

    public function register_settings(): void {
        register_setting(
            'scroll_magnet_settings_group',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );
    }

    public function register_settings_page(): void {
        add_options_page(
            'Scroll Magnet',
            'Scroll Magnet',
            'manage_options',
            'scroll-magnet',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_assets(): void {
        $settings = $this->get_settings();

        if (empty($settings['enabled']) || !$this->should_load_on_current_request($settings)) {
            return;
        }

        wp_enqueue_style(
            'scroll-magnet',
            plugin_dir_url(__FILE__) . 'assets/css/scroll-magnet.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'scroll-magnet',
            plugin_dir_url(__FILE__) . 'assets/js/scroll-magnet.js',
            [],
            self::VERSION,
            true
        );

        wp_localize_script(
            'scroll-magnet',
            'ScrollMagnetConfig',
            [
                'stages' => $this->build_frontend_stages($settings),
                'labels' => [
                    'close' => __('Fermer', 'scroll-magnet'),
                ],
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajaxNonce' => wp_create_nonce('scroll_magnet_track'),
            ]
        );
    }

    public function enqueue_admin_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'settings_page_scroll-magnet') {
            return;
        }

        wp_enqueue_style(
            'scroll-magnet-admin',
            plugin_dir_url(__FILE__) . 'assets/css/scroll-magnet-admin.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'scroll-magnet-admin',
            plugin_dir_url(__FILE__) . 'assets/js/scroll-magnet-admin.js',
            [],
            self::VERSION,
            true
        );
    }

    public function plugin_action_links(array $links): array {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=scroll-magnet')) . '">' . esc_html__('Settings', 'scroll-magnet') . '</a>';
        array_unshift($links, $settings_link);

        return $links;
    }

    public function sanitize_settings($input): array {
        $defaults = $this->get_default_settings();
        $input = is_array($input) ? $input : [];
        $sanitized = $defaults;

        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['target_scope'] = $this->sanitize_scope($input['target_scope'] ?? $defaults['target_scope']);
        $sanitized['specific_ids'] = $this->sanitize_specific_ids($input['specific_ids'] ?? '');

        foreach ([1, 2, 3] as $index) {
            $stage_key = 'stage_' . $index;
            $stage_input = isset($input[$stage_key]) && is_array($input[$stage_key]) ? $input[$stage_key] : [];
            $default_stage = $defaults[$stage_key];

            $sanitized[$stage_key] = [
                'enabled' => !empty($stage_input['enabled']),
                'percent' => $this->sanitize_percent($stage_input['percent'] ?? $default_stage['percent']),
                'type' => $this->sanitize_stage_type($stage_input['type'] ?? $default_stage['type']),
                'message' => sanitize_text_field($stage_input['message'] ?? $default_stage['message']),
                'button_label' => sanitize_text_field($stage_input['button_label'] ?? $default_stage['button_label']),
                'button_url' => esc_url_raw($stage_input['button_url'] ?? $default_stage['button_url']),
            ];
        }

        return $sanitized;
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        $stats = $this->get_stats();
        ?>
        <div class="wrap scroll-magnet-admin">
            <h1><?php echo esc_html__('Scroll Magnet', 'scroll-magnet'); ?></h1>
            <p><?php echo esc_html__('Des CTA de conversion déclenchés selon le niveau d’engagement réel du visiteur.', 'scroll-magnet'); ?></p>

            <div class="scroll-magnet-admin__hero">
                <div class="scroll-magnet-admin__hero-card">
                    <h2><?php echo esc_html__('Principe', 'scroll-magnet'); ?></h2>
                    <p><?php echo esc_html__('Plus un visiteur avance dans la page, plus l’appel à l’action devient direct. Le plugin affiche jusqu’à 3 messages progressifs selon le scroll.', 'scroll-magnet'); ?></p>
                </div>
                <div class="scroll-magnet-admin__hero-card">
                    <h2><?php echo esc_html__('Configuration rapide', 'scroll-magnet'); ?></h2>
                    <p><?php echo esc_html__('1. Active le plugin. 2. Choisis le ciblage. 3. Règle les 3 triggers. 4. Ajoute tes messages et liens.', 'scroll-magnet'); ?></p>
                </div>
                <div class="scroll-magnet-admin__hero-card">
                    <h2><?php echo esc_html__('Logique recommandée', 'scroll-magnet'); ?></h2>
                    <p><?php echo esc_html__('50%: découverte, 70%: réassurance, 90%: urgence. C’est ce schéma qui rend l’outil plus marketing qu’un popup classique.', 'scroll-magnet'); ?></p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('scroll_magnet_settings_group'); ?>

                <div class="scroll-magnet-admin__section">
                    <h2><?php echo esc_html__('Réglages globaux', 'scroll-magnet'); ?></h2>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Activer le plugin', 'scroll-magnet'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                                        <?php echo esc_html__('Afficher les CTA au scroll sur le front', 'scroll-magnet'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('Ciblage', 'scroll-magnet'); ?></th>
                                <td>
                                    <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[target_scope]">
                                        <?php foreach ($this->get_scope_options() as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['target_scope'], $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php echo esc_html__('Choisis où le plugin doit se déclencher.', 'scroll-magnet'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php echo esc_html__('IDs spécifiques', 'scroll-magnet'); ?></th>
                                <td>
                                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[specific_ids]" value="<?php echo esc_attr($settings['specific_ids']); ?>">
                                    <p class="description"><?php echo esc_html__('Optionnel. Liste d’IDs séparés par des virgules. Utilisé uniquement si le ciblage est “Pages spécifiques”.', 'scroll-magnet'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="scroll-magnet-admin__section">
                    <h2><?php echo esc_html__('Aperçu rapide', 'scroll-magnet'); ?></h2>
                    <p class="description"><?php echo esc_html__('L’aperçu se met à jour pendant que tu modifies les triggers pour visualiser la logique de conversion.', 'scroll-magnet'); ?></p>
                    <div class="scroll-magnet-admin__preview" id="scroll-magnet-preview"></div>
                </div>

                <?php foreach ([1, 2, 3] as $index) : ?>
                    <?php $stage = $settings['stage_' . $index]; ?>
                    <div class="scroll-magnet-admin__section">
                        <h2><?php echo esc_html(sprintf(__('Trigger %d', 'scroll-magnet'), $index)); ?></h2>
                        <p class="description"><?php echo esc_html($this->get_stage_hint($index)); ?></p>

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Actif', 'scroll-magnet'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][enabled]" value="1" <?php checked(!empty($stage['enabled'])); ?>>
                                            <?php echo esc_html__('Activer ce trigger', 'scroll-magnet'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('% de scroll', 'scroll-magnet'); ?></th>
                                    <td>
                                        <input type="number" min="5" max="100" step="1" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][percent]" value="<?php echo esc_attr((string) $stage['percent']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__("Type d'affichage", 'scroll-magnet'); ?></th>
                                    <td>
                                        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][type]">
                                            <?php foreach ($this->get_stage_types() as $value => $label) : ?>
                                                <option value="<?php echo esc_attr($value); ?>" <?php selected($stage['type'], $value); ?>>
                                                    <?php echo esc_html($label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Message', 'scroll-magnet'); ?></th>
                                    <td>
                                        <input type="text" class="large-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][message]" value="<?php echo esc_attr($stage['message']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Texte du bouton', 'scroll-magnet'); ?></th>
                                    <td>
                                        <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][button_label]" value="<?php echo esc_attr($stage['button_label']); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('URL du bouton', 'scroll-magnet'); ?></th>
                                    <td>
                                        <input type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[stage_<?php echo esc_attr((string) $index); ?>][button_url]" value="<?php echo esc_attr($stage['button_url']); ?>">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php submit_button(); ?>
            </form>

            <div class="scroll-magnet-admin__section">
                <h2><?php echo esc_html__('Statistiques MVP', 'scroll-magnet'); ?></h2>
                <p class="description"><?php echo esc_html__('Compteurs cumulés par trigger. Ces stats servent surtout à valider le potentiel avant une vraie version analytics plus poussée.', 'scroll-magnet'); ?></p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Trigger', 'scroll-magnet'); ?></th>
                            <th><?php echo esc_html__('Vues', 'scroll-magnet'); ?></th>
                            <th><?php echo esc_html__('Clics', 'scroll-magnet'); ?></th>
                            <th><?php echo esc_html__('CTR', 'scroll-magnet'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ([1, 2, 3] as $index) : ?>
                            <?php $stage_stats = $stats['stage_' . $index] ?? ['views' => 0, 'clicks' => 0]; ?>
                            <?php $views = (int) ($stage_stats['views'] ?? 0); ?>
                            <?php $clicks = (int) ($stage_stats['clicks'] ?? 0); ?>
                            <?php $ctr = $views > 0 ? round(($clicks / $views) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo esc_html(sprintf(__('Trigger %d', 'scroll-magnet'), $index)); ?></td>
                                <td><?php echo esc_html((string) $views); ?></td>
                                <td><?php echo esc_html((string) $clicks); ?></td>
                                <td><?php echo esc_html((string) $ctr); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function handle_track_event(): void {
        $nonce_valid = check_ajax_referer('scroll_magnet_track', 'nonce', false);
        if (!$nonce_valid) {
            wp_send_json_error(['message' => 'invalid_nonce'], 403);
        }

        $stage = isset($_POST['stage']) ? sanitize_key(wp_unslash($_POST['stage'])) : '';
        $event = isset($_POST['event']) ? sanitize_key(wp_unslash($_POST['event'])) : '';

        if (!in_array($stage, ['stage_1', 'stage_2', 'stage_3'], true)) {
            wp_send_json_error(['message' => 'invalid_stage'], 400);
        }

        if (!in_array($event, ['view', 'click'], true)) {
            wp_send_json_error(['message' => 'invalid_event'], 400);
        }

        $stats = $this->get_stats();
        if (!isset($stats[$stage]) || !is_array($stats[$stage])) {
            $stats[$stage] = ['views' => 0, 'clicks' => 0];
        }

        if ($event === 'view') {
            $stats[$stage]['views'] = (int) ($stats[$stage]['views'] ?? 0) + 1;
        } else {
            $stats[$stage]['clicks'] = (int) ($stats[$stage]['clicks'] ?? 0) + 1;
        }

        update_option(self::STATS_OPTION_KEY, $stats, false);
        wp_send_json_success(['stage' => $stage, 'event' => $event]);
    }

    public function inject_github_update($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release_data();
        if (!$release || empty($release['version'])) {
            return $transient;
        }

        if (version_compare(self::VERSION, $release['version'], '>=')) {
            return $transient;
        }

        $plugin_file = plugin_basename(__FILE__);
        $transient->response[$plugin_file] = (object) [
            'slug' => 'scroll-magnet',
            'plugin' => $plugin_file,
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'icons' => [],
            'banners' => [],
            'banners_rtl' => [],
            'tested' => '6.8',
            'requires_php' => '7.4',
            'compatibility' => new stdClass(),
        ];

        return $transient;
    }

    public function filter_plugin_information($result, string $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || empty($args->slug) || $args->slug !== 'scroll-magnet') {
            return $result;
        }

        $release = $this->get_github_release_data();
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'Scroll Magnet',
            'slug' => 'scroll-magnet',
            'version' => $release['version'],
            'author' => '<a href="https://github.com/juliansebastien-rgb">Le Labo d&#039;Azertaf</a>',
            'author_profile' => 'https://github.com/juliansebastien-rgb',
            'homepage' => self::GITHUB_REPOSITORY_URL,
            'requires' => '6.0',
            'requires_php' => '7.4',
            'tested' => '6.8',
            'last_updated' => $release['published_at'],
            'download_link' => $release['package'],
            'sections' => [
                'description' => 'Behavior-based conversion CTAs triggered by visitor scroll depth.',
                'installation' => 'Upload the plugin, activate it, then go to Settings > Scroll Magnet to configure your three CTA triggers.',
                'changelog' => sprintf("= %s =\n* GitHub release package.\n", $release['version']),
            ],
            'banners' => [],
            'icons' => [],
        ];
    }

    public function clear_update_cache($upgrader, array $hook_extra): void {
        if (($hook_extra['type'] ?? '') !== 'plugin') {
            return;
        }

        $plugins = $hook_extra['plugins'] ?? [];
        if (in_array(plugin_basename(__FILE__), $plugins, true)) {
            delete_transient(self::TRANSIENT_PREFIX . 'github_release');
        }
    }

    public function normalize_github_update_source(string $source, string $remote_source, $upgrader, array $hook_extra): string {
        if (($hook_extra['type'] ?? '') !== 'plugin') {
            return $source;
        }

        $plugins = $hook_extra['plugins'] ?? [];
        if (!in_array(plugin_basename(__FILE__), $plugins, true)) {
            return $source;
        }

        $normalized = trailingslashit($remote_source) . 'scroll-magnet';
        if ($source === $normalized || !is_dir($source)) {
            return $source;
        }

        if (@rename($source, $normalized)) {
            return $normalized;
        }

        return $source;
    }

    private function get_settings(): array {
        $settings = get_option(self::OPTION_KEY, []);
        $settings = is_array($settings) ? $settings : [];

        return wp_parse_args($settings, $this->get_default_settings());
    }

    private function get_stats(): array {
        $stats = get_option(self::STATS_OPTION_KEY, []);

        if (!is_array($stats)) {
            $stats = [];
        }

        foreach ([1, 2, 3] as $index) {
            $key = 'stage_' . $index;
            if (!isset($stats[$key]) || !is_array($stats[$key])) {
                $stats[$key] = ['views' => 0, 'clicks' => 0];
            }
        }

        return $stats;
    }

    private function get_default_settings(): array {
        return [
            'enabled' => true,
            'target_scope' => 'all',
            'specific_ids' => '',
            'stage_1' => [
                'enabled' => true,
                'percent' => 50,
                'type' => 'banner',
                'message' => __('Vous êtes arrivé jusqu’ici. Profitez de -10% maintenant.', 'scroll-magnet'),
                'button_label' => __('Voir l’offre', 'scroll-magnet'),
                'button_url' => home_url('/'),
            ],
            'stage_2' => [
                'enabled' => true,
                'percent' => 70,
                'type' => 'floating_button',
                'message' => __('Encore un petit effort. Votre offre vous attend.', 'scroll-magnet'),
                'button_label' => __('Je découvre', 'scroll-magnet'),
                'button_url' => home_url('/'),
            ],
            'stage_3' => [
                'enabled' => true,
                'percent' => 90,
                'type' => 'popup',
                'message' => __('Dernière chance avant de partir. Activez votre avantage maintenant.', 'scroll-magnet'),
                'button_label' => __('Activer mon offre', 'scroll-magnet'),
                'button_url' => home_url('/'),
            ],
        ];
    }

    private function build_frontend_stages(array $settings): array {
        $stages = [];

        foreach ([1, 2, 3] as $index) {
            $stage = $settings['stage_' . $index];

            if (empty($stage['enabled'])) {
                continue;
            }

            $stages[] = [
                'id' => 'stage-' . $index,
                'trackKey' => 'stage_' . $index,
                'percent' => (int) $stage['percent'],
                'type' => $stage['type'],
                'message' => $stage['message'],
                'buttonLabel' => $stage['button_label'],
                'buttonUrl' => $stage['button_url'],
            ];
        }

        usort(
            $stages,
            static function (array $left, array $right): int {
                return $left['percent'] <=> $right['percent'];
            }
        );

        return $stages;
    }

    private function should_load_on_current_request(array $settings): bool {
        if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
            return false;
        }

        if (!is_singular()) {
            return false;
        }

        $scope = $settings['target_scope'] ?? 'all';

        if ($scope === 'all') {
            return true;
        }

        if ($scope === 'posts') {
            return is_single();
        }

        if ($scope === 'pages') {
            return is_page();
        }

        if ($scope === 'specific') {
            $ids = array_filter(array_map('absint', explode(',', (string) ($settings['specific_ids'] ?? ''))));

            return !empty($ids) && in_array(get_queried_object_id(), $ids, true);
        }

        return false;
    }

    private function sanitize_scope($value): string {
        $value = is_string($value) ? $value : 'all';
        $allowed = array_keys($this->get_scope_options());

        return in_array($value, $allowed, true) ? $value : 'all';
    }

    private function sanitize_percent($value): int {
        $percent = (int) $value;

        if ($percent < 5) {
            return 5;
        }

        if ($percent > 100) {
            return 100;
        }

        return $percent;
    }

    private function sanitize_stage_type($value): string {
        $value = is_string($value) ? $value : 'banner';
        $allowed = array_keys($this->get_stage_types());

        return in_array($value, $allowed, true) ? $value : 'banner';
    }

    private function sanitize_specific_ids($value): string {
        if (!is_string($value)) {
            return '';
        }

        $ids = array_filter(array_map('absint', preg_split('/\s*,\s*/', trim($value))));

        return implode(',', $ids);
    }

    private function get_scope_options(): array {
        return [
            'all' => __('Toutes les pages et articles', 'scroll-magnet'),
            'posts' => __('Articles uniquement', 'scroll-magnet'),
            'pages' => __('Pages uniquement', 'scroll-magnet'),
            'specific' => __('Pages spécifiques', 'scroll-magnet'),
        ];
    }

    private function get_stage_types(): array {
        return [
            'banner' => __('Bandeau bas', 'scroll-magnet'),
            'floating_button' => __('Bouton flottant', 'scroll-magnet'),
            'popup' => __('Popup douce', 'scroll-magnet'),
        ];
    }

    private function get_stage_hint(int $index): string {
        if ($index === 1) {
            return __('Premier contact: propose une découverte ou un bénéfice simple.', 'scroll-magnet');
        }

        if ($index === 2) {
            return __('Milieu de lecture: rassure et pousse un clic léger.', 'scroll-magnet');
        }

        return __('Fin de lecture: ajoute une notion d’urgence ou de dernière chance.', 'scroll-magnet');
    }

    private function get_github_release_data(): ?array {
        $cache_key = self::TRANSIENT_PREFIX . 'github_release';
        $cached = get_transient($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $release = $this->request_github_release('/releases/latest');

        if (!$release) {
            $tag = $this->request_github_release('/tags');
            if (!$tag || empty($tag[0]['name'])) {
                return null;
            }

            $first_tag = $tag[0];
            $release = [
                'tag_name' => $first_tag['name'],
                'zipball_url' => self::GITHUB_API_BASE . '/zipball/' . rawurlencode($first_tag['name']),
                'html_url' => self::GITHUB_REPOSITORY_URL . '/releases/tag/' . rawurlencode($first_tag['name']),
                'published_at' => gmdate('Y-m-d H:i:s'),
                'body' => '',
            ];
        }

        if (empty($release['tag_name'])) {
            return null;
        }

        $package = '';
        if (!empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (!is_array($asset)) {
                    continue;
                }

                $name = isset($asset['name']) ? (string) $asset['name'] : '';
                $download = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';

                if ($name !== '' && substr($name, -4) === '.zip' && $download !== '') {
                    $package = $download;
                    break;
                }
            }
        }

        if ($package === '' && !empty($release['zipball_url'])) {
            $package = (string) $release['zipball_url'];
        }

        if ($package === '') {
            return null;
        }

        $data = [
            'version' => ltrim((string) $release['tag_name'], 'v'),
            'package' => $package,
            'url' => !empty($release['html_url']) ? (string) $release['html_url'] : self::GITHUB_REPOSITORY_URL,
            'published_at' => !empty($release['published_at']) ? gmdate('Y-m-d H:i:s', strtotime((string) $release['published_at'])) : gmdate('Y-m-d H:i:s'),
            'body' => !empty($release['body']) ? (string) $release['body'] : '',
        ];

        set_transient($cache_key, $data, self::UPDATE_CACHE_TTL);

        return $data;
    }

    private function request_github_release(string $path) {
        $response = wp_remote_get(
            self::GITHUB_API_BASE . $path,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Scroll Magnet/' . self::VERSION . '; ' . home_url('/'),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($data) ? $data : null;
    }
}

$scroll_magnet = new Scroll_Magnet();
$scroll_magnet->boot();
