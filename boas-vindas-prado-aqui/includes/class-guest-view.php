<?php
/**
 * Public Guest Guide View and Router for Boas-Vindas Prado Aqui
 */

if (!defined('ABSPATH')) {
    exit;
}

class Prado_Welcome_Guest_View {

    /**
     * Set up template redirection hook
     */
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'handle_routing'));
    }

    /**
     * Intercept requests starting with /g/TOKEN
     */
    public static function handle_routing() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);

        // Normalize path in case of subdirectory installations
        $plugin_slug = '/g/';
        $pos = strpos($path, $plugin_slug);
        
        if ($pos !== false) {
            $token_part = substr($path, $pos + strlen($plugin_slug));
            $token = trim($token_part, '/');
            
            // Clean token from any trailing query parameters or path segments
            if (strpos($token, '?') !== false) {
                $token = substr($token, 0, strpos($token, '?'));
            }
            
            if (!empty($token) && preg_match('/^[a-zA-Z0-9]+$/', $token)) {
                $view = new self();
                $view->render($token);
                exit;
            }
        }
    }

    /**
     * Render the guest guide or expired page
     */
    public function render($token) {
        global $wpdb;

        $table_tokens       = Prado_Welcome_Database::get_table_name('tokens');
        $table_guides       = Prado_Welcome_Database::get_table_name('guides');
        $table_reservations = Prado_Welcome_Database::get_table_name('reservations');
        $table_properties   = Prado_Welcome_Database::get_table_name('properties');
        $table_content      = Prado_Welcome_Database::get_table_name('content');
        $table_contacts     = Prado_Welcome_Database::get_table_name('contacts');
        $table_places       = Prado_Welcome_Database::get_table_name('places');

        // 1. Check if token exists
        $token_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_tokens WHERE token = %s",
            $token
        ));

        if (!$token_row) {
            $this->render_expired('Token não encontrado ou inválido.');
            return;
        }

        // 2. Check if guide exists
        $guide = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_guides WHERE id = %d",
            $token_row->guide_id
        ));

        if (!$guide) {
            $this->render_expired('Guia digital não encontrado.');
            return;
        }

        // 3. Check if reservation exists
        $reservation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_reservations WHERE id = %d",
            $guide->reservation_id
        ));

        if (!$reservation) {
            $this->render_expired('Reserva correspondente não encontrada.');
            return;
        }

        // 4. Validate activation status
        if ($guide->status !== 'active') {
            $this->render_expired('Este guia foi revogado pelo proprietário.');
            return;
        }

        // 5. Validate dates
        $today = date('Y-m-d');
        // Let's grant access on check-in date and expire 1 day after check-out date (grace period)
        $checkin = $reservation->checkin_date;
        $checkout = $reservation->checkout_date;
        
        if ($today < $checkin) {
            $this->render_early($checkin, $reservation->guest_name);
            return;
        }

        if ($today > $checkout) {
            // Automatically update guide status to expired in db
            $wpdb->update($table_guides, array('status' => 'expired', 'expiration_date' => current_time('mysql')), array('id' => $guide->id));
            $this->render_expired();
            return;
        }

        // 6. Fetch property & content details
        $property = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_properties WHERE id = %d",
            $reservation->property_id
        ));

        if (!$property) {
            $this->render_expired('Imóvel não encontrado.');
            return;
        }

        // Fetch all contents for the property
        $contents_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT content_type, content_data FROM $table_content WHERE property_id = %d",
            $property->id
        ));

        $contents = array();
        foreach ($contents_raw as $c) {
            $contents[$c->content_type] = json_decode($c->content_data, true);
        }

        // Fetch contacts
        $contacts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_contacts WHERE property_id = %d",
            $property->id
        ));

        // Fetch places
        $places = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_places WHERE property_id = %d",
            $property->id
        ));

        // Render Page
        $this->render_guide_html($property, $reservation, $contents, $contacts, $places);
    }

    /**
     * Render the guide page HTML
     */
    private function render_guide_html($property, $reservation, $contents, $contacts, $places) {
        $assets_url = plugins_url('assets/', dirname(__FILE__));
        
        // Setup default content sections if missing
        $welcome = isset($contents['welcome']) ? $contents['welcome'] : array('title' => 'Seja bem-vindo!', 'message' => '', 'host_name' => $property->hostname, 'signature' => '');
        $checkin = isset($contents['checkin']) ? $contents['checkin'] : array();
        $wifi = isset($contents['wifi']) ? $contents['wifi'] : array('ssid' => '', 'password' => '');
        $rules = isset($contents['rules']) ? $contents['rules'] : array();
        $checkout = isset($contents['checkout']) ? $contents['checkout'] : array();
        $structure = isset($contents['structure']) ? $contents['structure'] : array();
        $how_to_use = isset($contents['how_to_use']) ? $contents['how_to_use'] : array();

        // Get guest firstName
        $guest_name = $reservation->guest_name;
        $guest_first_name = explode(' ', trim($guest_name))[0];

        // Format dates
        $in_date = date('d/m', strtotime($reservation->checkin_date));
        $out_date = date('d/m', strtotime($reservation->checkout_date));

        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Guia de Hospedagem - <?php echo esc_html($property->name); ?></title>
            <!-- Outfit Font from Google Fonts -->
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            <!-- FontAwesome for Premium Icons -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <!-- Custom CSS -->
            <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/guest-guide.css'); ?>?ver=1.0.0">
        </head>
        <body>
            <div class="guide-container">
                
                <!-- Main Header / Hero Section -->
                <header class="guide-hero" style="background-image: linear-gradient(180deg, rgba(0, 0, 0, 0.2) 0%, rgba(15, 23, 42, 0.95) 85%, #0f172a 100%), url('<?php echo esc_url($property->photo_main ? $property->photo_main : $assets_url . 'images/property-placeholder.jpg'); ?>');">
                    <div class="hero-logo-wrapper">
                        <?php if ($property->logo): ?>
                            <img src="<?php echo esc_url($property->logo); ?>" alt="Logo" class="hero-logo">
                        <?php else: ?>
                            <div class="hero-logo-empty"><i class="fa-solid fa-hotel"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="hero-details">
                        <span class="badge-status"><span class="pulse-dot"></span> Guia Ativo</span>
                        <h1 class="welcome-title">Seja bem-vindo, <?php echo esc_html($guest_first_name); ?>!</h1>
                        <p class="property-name"><?php echo esc_html($property->name); ?></p>
                        <p class="reservation-dates">
                            <i class="fa-regular fa-calendar-days"></i> <?php echo esc_html($in_date); ?> a <?php echo esc_html($out_date); ?> &bull; <?php echo esc_html($reservation->guest_pax); ?> <?php echo $reservation->guest_pax > 1 ? 'hóspedes' : 'hóspede'; ?>
                        </p>
                    </div>
                </header>

                <!-- Navigation Tabs (Dynamic Panel Switching) -->
                <nav class="guide-nav">
                    <button class="nav-tab active" data-target="panel-home">
                        <i class="fa-solid fa-house"></i>
                        <span>Início</span>
                    </button>
                    <button class="nav-tab" data-target="panel-house">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Utilização</span>
                    </button>
                    <button class="nav-tab" data-target="panel-tourism">
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Região</span>
                    </button>
                    <button class="nav-tab" data-target="panel-checkout">
                        <i class="fa-solid fa-sign-out-alt"></i>
                        <span>Checkout</span>
                    </button>
                </nav>

                <!-- Main Panels -->
                <main class="guide-panels">

                    <!-- 1. Home / General Panel -->
                    <div class="guide-panel active" id="panel-home">
                        
                        <!-- Host Welcome Card -->
                        <section class="guide-card welcome-card">
                            <div class="host-profile">
                                <?php if (!empty($welcome['host_photo'])): ?>
                                    <img src="<?php echo esc_url($welcome['host_photo']); ?>" alt="Host" class="host-avatar">
                                <?php else: ?>
                                    <div class="host-avatar-empty"><i class="fa-solid fa-user-tie"></i></div>
                                <?php endif; ?>
                                <div class="host-info">
                                    <h3 class="host-name"><?php echo esc_html(!empty($welcome['host_name']) ? $welcome['host_name'] : $property->hostname); ?></h3>
                                    <span class="host-title">Seu Anfitrião</span>
                                </div>
                            </div>
                            <div class="welcome-message-content">
                                <h4 class="welcome-msg-title"><?php echo esc_html(!empty($welcome['title']) ? $welcome['title'] : 'Olá!'); ?></h4>
                                <p class="welcome-msg-text"><?php echo nl2br(esc_html($welcome['message'])); ?></p>
                                <?php if (!empty($welcome['signature'])): ?>
                                    <div class="host-signature"><?php echo esc_html($welcome['signature']); ?></div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Wi-Fi Card (Direct copy feature) -->
                        <?php if (!empty($wifi['ssid'])): ?>
                            <section class="guide-card wifi-card">
                                <div class="card-header">
                                    <div class="header-icon"><i class="fa-solid fa-wifi"></i></div>
                                    <h3>Internet Wi-Fi</h3>
                                </div>
                                <div class="wifi-details">
                                    <div class="wifi-field">
                                        <span class="field-label">Rede:</span>
                                        <span class="field-value font-semibold" id="wifi-ssid"><?php echo esc_html($wifi['ssid']); ?></span>
                                    </div>
                                    <div class="wifi-field">
                                        <span class="field-label">Senha:</span>
                                        <span class="field-value font-semibold" id="wifi-pass"><?php echo esc_html($wifi['password']); ?></span>
                                    </div>
                                    <?php if (!empty($wifi['notes'])): ?>
                                        <p class="wifi-notes"><i class="fa-solid fa-info-circle"></i> <?php echo esc_html($wifi['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-primary btn-block" id="btn-copy-wifi">
                                    <i class="fa-solid fa-copy"></i> Copiar Senha
                                </button>
                            </section>
                        <?php endif; ?>

                        <!-- Quick Actions Grid -->
                        <div class="quick-actions-grid">
                            <?php if ($property->location_link): ?>
                                <a href="<?php echo esc_url($property->location_link); ?>" target="_blank" class="quick-action-btn">
                                    <i class="fa-solid fa-location-arrow"></i>
                                    <span>Como Chegar</span>
                                </a>
                            <?php endif; ?>
                            
                            <button class="quick-action-btn" id="btn-show-checkin">
                                <i class="fa-solid fa-key"></i>
                                <span>Check-in</span>
                            </button>

                            <button class="quick-action-btn" id="btn-show-contacts">
                                <i class="fa-solid fa-phone"></i>
                                <span>Contatos</span>
                            </button>

                            <button class="quick-action-btn" id="btn-show-rules">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>Regras</span>
                            </button>
                        </div>

                        <!-- Property Details -->
                        <section class="guide-card">
                            <div class="card-header">
                                <div class="header-icon"><i class="fa-solid fa-circle-info"></i></div>
                                <h3>Sobre a Hospedagem</h3>
                            </div>
                            <p class="property-desc"><?php echo nl2br(esc_html($property->description)); ?></p>
                            
                            <?php if (!empty($structure)): ?>
                                <h4 class="sub-section-title">Comodidades Disponíveis</h4>
                                <div class="amenities-tags">
                                    <?php foreach ($structure as $item): ?>
                                        <span class="amenity-tag">
                                            <i class="fa-solid fa-check-circle"></i> <?php echo esc_html(ucfirst(str_replace('_', ' ', $item))); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <!-- 2. Items & Usage Panel (How to Use) -->
                    <div class="guide-panel" id="panel-house">
                        <section class="panel-intro">
                            <h2>Instruções de Uso</h2>
                            <p>Saiba como utilizar corretamente os eletrodomésticos, aparelhos e comodidades da hospedagem.</p>
                        </section>

                        <?php if (!empty($how_to_use)): ?>
                            <div class="how-to-list">
                                <?php foreach ($how_to_use as $index => $item): ?>
                                    <div class="guide-card how-to-item">
                                        <div class="how-to-header" data-toggle="howto-<?php echo $index; ?>">
                                            <div class="how-to-icon-name">
                                                <?php if (!empty($item['photo'])): ?>
                                                    <img src="<?php echo esc_url($item['photo']); ?>" alt="" class="howto-thumb">
                                                <?php else: ?>
                                                    <div class="howto-thumb-empty"><i class="fa-solid fa-cube"></i></div>
                                                <?php endif; ?>
                                                <h3><?php echo esc_html($item['name']); ?></h3>
                                            </div>
                                            <div class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></div>
                                        </div>
                                        <div class="how-to-body" id="howto-<?php echo $index; ?>">
                                            <?php if (!empty($item['description'])): ?>
                                                <p class="howto-desc"><?php echo esc_html($item['description']); ?></p>
                                            <?php endif; ?>
                                            <div class="howto-instructions">
                                                <h4><i class="fa-solid fa-book-open"></i> Como usar:</h4>
                                                <p><?php echo nl2br(esc_html($item['instructions'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-sliders"></i>
                                <p>Nenhuma instrução de uso cadastrada pelo anfitrião.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Tourism & Region Panel -->
                    <div class="guide-panel" id="panel-tourism">
                        <section class="panel-intro">
                            <h2>O que fazer na região</h2>
                            <p>Confira as nossas melhores recomendações de restaurantes, passeios, mercados e pontos turísticos locais.</p>
                        </section>

                        <!-- Filters for Tourism Items -->
                        <div class="tourism-filters">
                            <button class="filter-tab active" data-filter="all">Todos</button>
                            <button class="filter-tab" data-filter="gastronomy">Gastronomia</button>
                            <button class="filter-tab" data-filter="tours">Passeios</button>
                            <button class="filter-tab" data-filter="services">Utilidades</button>
                        </div>

                        <?php if (!empty($places)): ?>
                            <div class="places-grid">
                                <?php foreach ($places as $place): 
                                    // Map places database type to filter category
                                    $category = 'services';
                                    if (in_array($place->type, array('restaurant', 'bar', 'experience', 'shopping'))) {
                                        $category = 'gastronomy';
                                    } elseif (in_array($place->type, array('waterfall', 'river', 'fishing', 'tour', 'sightseeing'))) {
                                        $category = 'tours';
                                    }
                                    ?>
                                    <div class="guide-card place-card" data-category="<?php echo esc_attr($category); ?>">
                                        <?php if ($place->image): ?>
                                            <div class="place-img" style="background-image: url('<?php echo esc_url($place->image); ?>');"></div>
                                        <?php endif; ?>
                                        <div class="place-content">
                                            <span class="place-type-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $place->type))); ?></span>
                                            <h3><?php echo esc_html($place->name); ?></h3>
                                            <p class="place-desc"><?php echo esc_html($place->description); ?></p>
                                            
                                            <?php if ($place->address): ?>
                                                <p class="place-info-line"><i class="fa-solid fa-map-marker-alt"></i> <?php echo esc_html($place->address); ?></p>
                                            <?php endif; ?>
                                            <?php if ($place->phone): ?>
                                                <p class="place-info-line"><i class="fa-solid fa-phone"></i> <?php echo esc_html($place->phone); ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="place-actions">
                                                <?php if ($place->location): ?>
                                                    <a href="<?php echo esc_url($place->location); ?>" target="_blank" class="btn btn-outline btn-sm">
                                                        <i class="fa-solid fa-map"></i> Ver Mapa
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($place->link): ?>
                                                    <a href="<?php echo esc_url($place->link); ?>" target="_blank" class="btn btn-outline btn-sm">
                                                        <i class="fa-solid fa-globe"></i> Acessar Site
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-map-marked"></i>
                                <p>Nenhum local turístico ou comercial recomendado ainda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Checkout Panel -->
                    <div class="guide-panel" id="panel-checkout">
                        <section class="panel-intro">
                            <h2>Procedimentos de Saída</h2>
                            <p>Garantir o cumprimento dessas etapas nos ajuda a manter a hospedagem incrível para os próximos hóspedes.</p>
                        </section>

                        <section class="guide-card checkout-header-card">
                            <div class="checkout-time-badge">
                                <i class="fa-regular fa-clock"></i>
                                <span>Horário limite de Checkout: <strong><?php echo esc_html(!empty($checkout['checkout_time']) ? $checkout['checkout_time'] : '12:00'); ?></strong></span>
                            </div>
                        </section>

                        <section class="guide-card">
                            <h3>Checklist de Saída</h3>
                            <p class="checkout-instruction">Marque os itens conforme for concluindo:</p>
                            
                            <div class="checkout-checklist">
                                <?php
                                $chk_items = array(
                                    'key_instructions' => 'Chave: ' . (!empty($checkout['key_instructions']) ? $checkout['key_instructions'] : 'Deixar no local combinado.'),
                                    'trash' => !empty($checkout['trash']) ? 'Lixo: ' . $checkout['trash'] : 'Retirar o lixo e descartar nas lixeiras externas.',
                                    'lights' => !empty($checkout['lights']) ? 'Luzes: ' . $checkout['lights'] : 'Apagar todas as luzes e ventiladores.',
                                    'ac' => !empty($checkout['ac']) ? 'Ar-condicionado: ' . $checkout['ac'] : 'Desligar todos os aparelhos de ar-condicionado.',
                                    'doors' => !empty($checkout['doors']) ? 'Portas e Portões: ' . $checkout['doors'] : 'Trancar portas e janelas ao sair.',
                                    'windows' => !empty($checkout['windows']) ? 'Janelas: ' . $checkout['windows'] : 'Verificar se todas as janelas estão trancadas.',
                                    'dishes' => !empty($checkout['dishes']) ? 'Louças: ' . $checkout['dishes'] : 'Lavar e guardar as louças utilizadas.',
                                    'personal_objects' => !empty($checkout['personal_objects']) ? 'Objetos Pessoais: ' . $checkout['personal_objects'] : 'Verificar armários e tomadas para não esquecer pertences.',
                                );
                                
                                foreach ($chk_items as $key => $label): ?>
                                    <label class="checklist-item">
                                        <input type="checkbox" name="checkout_step" value="<?php echo esc_attr($key); ?>">
                                        <span class="custom-checkbox"></span>
                                        <span class="checklist-text"><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($checkout['departure_notice'])): ?>
                                <div class="checkout-alert">
                                    <i class="fa-solid fa-bell"></i>
                                    <p><?php echo esc_html($checkout['departure_notice']); ?></p>
                                </div>
                            <?php endif; ?>

                            <button class="btn btn-success btn-block" id="btn-notify-checkout">
                                <i class="fa-solid fa-paper-plane"></i> Notificar Saída
                            </button>
                        </section>
                    </div>

                </main>

                <!-- Overlay Sheets (Check-in, Contacts, Rules) -->
                
                <!-- CHECK-IN SHEET -->
                <div class="overlay-sheet" id="sheet-checkin">
                    <div class="sheet-backdrop"></div>
                    <div class="sheet-content">
                        <div class="sheet-header">
                            <h3><i class="fa-solid fa-key"></i> Instruções de Check-in</h3>
                            <button class="btn-close-sheet"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="sheet-body">
                            <div class="checkin-time-badge">
                                <i class="fa-regular fa-clock"></i> Horário de entrada: <strong><?php echo esc_html(!empty($checkin['checkin_time']) ? $checkin['checkin_time'] : '14:00'); ?></strong>
                            </div>
                            
                            <?php if (!empty($checkin['instructions'])): ?>
                                <div class="info-group">
                                    <h4>Como acessar a hospedagem:</h4>
                                    <p><?php echo nl2br(esc_html($checkin['instructions'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($checkin['digital_lock']) && $checkin['digital_lock'] === 'yes'): ?>
                                <div class="info-group lock-code-card">
                                    <h4><i class="fa-solid fa-lock"></i> Fechadura Digital</h4>
                                    <div class="digital-code"><?php echo esc_html(!empty($checkin['code']) ? $checkin['code'] : 'Solicite a senha.'); ?></div>
                                    <p class="digital-lock-notes">Digite a senha seguida de # ou conforme painel.</p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($checkin['gate_info'])): ?>
                                <div class="info-group">
                                    <h4><i class="fa-solid fa-door-open"></i> Portão / Acesso Geral:</h4>
                                    <p><?php echo esc_html($checkin['gate_info']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($checkin['parking'])): ?>
                                <div class="info-group">
                                    <h4><i class="fa-solid fa-car"></i> Estacionamento e Garagem:</h4>
                                    <p><?php echo esc_html($checkin['parking']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($checkin['reference_point'])): ?>
                                <div class="info-group">
                                    <h4><i class="fa-solid fa-compass"></i> Ponto de Referência:</h4>
                                    <p><?php echo esc_html($checkin['reference_point']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($checkin['other_instructions'])): ?>
                                <div class="info-group">
                                    <h4>Instruções Adicionais:</h4>
                                    <p><?php echo nl2br(esc_html($checkin['other_instructions'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RULES SHEET -->
                <div class="overlay-sheet" id="sheet-rules">
                    <div class="sheet-backdrop"></div>
                    <div class="sheet-content">
                        <div class="sheet-header">
                            <h3><i class="fa-solid fa-circle-exclamation"></i> Regras da Casa</h3>
                            <button class="btn-close-sheet"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="sheet-body">
                            <?php if (!empty($rules)): ?>
                                <div class="rules-list">
                                    <?php foreach ($rules as $rule_key => $rule_val): 
                                        $label = ucfirst(str_replace('_', ' ', $rule_key));
                                        $icon = 'fa-circle-chevron-right';
                                        
                                        // Specific icons for rules keys
                                        if (stripos($rule_key, 'pet') !== false) { $icon = 'fa-paw'; }
                                        elseif (stripos($rule_key, 'fum') !== false) { $icon = 'fa-smoking-ban'; }
                                        elseif (stripos($rule_key, 'fest') !== false || stripos($rule_key, 'event') !== false) { $icon = 'fa-glass-cheers'; }
                                        elseif (stripos($rule_key, 'silenc') !== false) { $icon = 'fa-volume-mute'; }
                                        elseif (stripos($rule_key, 'pisc') !== false) { $icon = 'fa-person-swimming'; }
                                        elseif (stripos($rule_key, 'limit') !== false || stripos($rule_key, 'pax') !== false) { $icon = 'fa-users-slash'; }
                                        ?>
                                        <div class="rule-item">
                                            <div class="rule-icon"><i class="fa-solid <?php echo esc_attr($icon); ?>"></i></div>
                                            <div class="rule-content">
                                                <h4><?php echo esc_html($label); ?></h4>
                                                <p><?php echo esc_html($rule_val); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fa-solid fa-scale-balanced"></i>
                                    <p>Regras básicas de convivência se aplicam. Nenhuma regra específica listada.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- CONTACTS SHEET -->
                <div class="overlay-sheet" id="sheet-contacts">
                    <div class="sheet-backdrop"></div>
                    <div class="sheet-content">
                        <div class="sheet-header">
                            <h3><i class="fa-solid fa-phone"></i> Telefones e Contatos Úteis</h3>
                            <button class="btn-close-sheet"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="sheet-body">
                            
                            <!-- Host Main Contact -->
                            <div class="contact-section-title">Anfitrião</div>
                            <div class="contact-card-item">
                                <div class="contact-avatar"><i class="fa-solid fa-user-shield"></i></div>
                                <div class="contact-info">
                                    <h4><?php echo esc_html($property->hostname); ?></h4>
                                    <p>Anfitrião Principal</p>
                                </div>
                                <div class="contact-actions">
                                    <?php if ($property->host_phone): ?>
                                        <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $property->host_phone)); ?>" class="btn-circle-action bg-primary"><i class="fa-solid fa-phone"></i></a>
                                    <?php endif; ?>
                                    <?php if ($property->host_whatsapp): ?>
                                        <a href="https://wa.me/55<?php echo esc_attr(preg_replace('/\D/', '', $property->host_whatsapp)); ?>" target="_blank" class="btn-circle-action bg-success"><i class="fa-brands fa-whatsapp"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- List of Emergency / Support Contacts -->
                            <?php if (!empty($contacts)): ?>
                                <div class="contact-section-title">Outros Contatos / Emergência</div>
                                <div class="contacts-list">
                                    <?php foreach ($contacts as $c): 
                                        $icon = 'fa-phone';
                                        $bg_class = 'bg-primary';
                                        
                                        if (stripos($c->type, 'hospital') !== false || stripos($c->type, 'medic') !== false) { $icon = 'fa-square-h'; $bg_class = 'bg-danger'; }
                                        elseif (stripos($c->type, 'polic') !== false) { $icon = 'fa-shield-halved'; $bg_class = 'bg-info'; }
                                        elseif (stripos($c->type, 'bombeir') !== false) { $icon = 'fa-fire-extinguisher'; $bg_class = 'bg-danger'; }
                                        elseif (stripos($c->type, 'supermerc') !== false || stripos($c->type, 'mercad') !== false) { $icon = 'fa-basket-shopping'; }
                                        elseif (stripos($c->type, 'gas') !== false || stripos($c->type, 'posto') !== false) { $icon = 'fa-gas-pump'; }
                                        elseif (stripos($c->type, 'chaveir') !== false) { $icon = 'fa-key'; }
                                        ?>
                                        <div class="contact-card-item">
                                            <div class="contact-avatar"><i class="fa-solid <?php echo esc_attr($icon); ?>"></i></div>
                                            <div class="contact-info">
                                                <h4><?php echo esc_html($c->name); ?></h4>
                                                <p><?php echo esc_html(ucfirst(str_replace('_', ' ', $c->type))); ?> <?php echo $c->notes ? '&bull; ' . esc_html($c->notes) : ''; ?></p>
                                            </div>
                                            <div class="contact-actions">
                                                <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $c->phone)); ?>" class="btn-circle-action <?php echo esc_attr($bg_class); ?>"><i class="fa-solid fa-phone"></i></a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer Details -->
            <footer class="main-footer">
                <p>Boas-Vindas Prado Aqui &copy; <?php echo date('Y'); ?></p>
                <p class="developer-footer">Desenvolvido com carinho para sua estadia.</p>
            </footer>

            <!-- Pass config details to guest-guide.js -->
            <script>
                const guestGuideConfig = {
                    hostPhone: '<?php echo esc_js($property->host_phone); ?>',
                    hostWhatsapp: '<?php echo esc_js($property->host_whatsapp); ?>',
                    guestName: '<?php echo esc_js($guest_name); ?>',
                    propertyName: '<?php echo esc_js($property->name); ?>'
                };
            </script>
            <!-- Custom JavaScript -->
            <script src="<?php echo esc_url($assets_url . 'js/guest-guide.js'); ?>?ver=1.0.0"></script>
        </body>
        </html>
        <?php
    }

    /**
     * Render the early access template
     */
    private function render_early($checkin_date, $guest_name) {
        $assets_url = plugins_url('assets/', dirname(__FILE__));
        $date_formatted = date('d/m/Y', strtotime($checkin_date));
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Guia de Hospedagem - Pré-Acesso</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/guest-guide.css'); ?>?ver=1.0.0">
        </head>
        <body class="bg-dark-full">
            <div class="lockout-container">
                <div class="lockout-logo"><i class="fa-solid fa-hourglass-start"></i></div>
                <h1>Quase pronto, <?php echo esc_html(explode(' ', trim($guest_name))[0]); ?>!</h1>
                <p class="lockout-msg">O seu Guia Digital de hospedagem estará disponível a partir do dia <strong><?php echo esc_html($date_formatted); ?></strong>.</p>
                <div class="lockout-divider"></div>
                <p class="lockout-sub">Preparamos todos os detalhes para sua estadia com muito carinho!</p>
                <footer class="lockout-footer">Boas-Vindas Prado Aqui</footer>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render the expired guide page HTML
     */
    private function render_expired($debug_msg = '') {
        $assets_url = plugins_url('assets/', dirname(__FILE__));
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Guia de Hospedagem Encerrado</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="<?php echo esc_url($assets_url . 'css/guest-guide.css'); ?>?ver=1.0.0">
        </head>
        <body class="bg-dark-full">
            <div class="lockout-container">
                <div class="lockout-logo"><i class="fa-solid fa-lock"></i></div>
                <h1>Este Guia Digital foi encerrado.</h1>
                <p class="lockout-msg">O período desta reserva terminou.</p>
                <div class="lockout-divider"></div>
                <p class="lockout-sub">Obrigado pela visita!</p>
                
                <?php if (defined('WP_DEBUG') && WP_DEBUG && !empty($debug_msg)): ?>
                    <div style="margin-top: 20px; font-size: 11px; color: #64748b;">
                        Debug: <?php echo esc_html($debug_msg); ?>
                    </div>
                <?php endif; ?>
                
                <footer class="lockout-footer">Boas-Vindas Prado Aqui</footer>
            </div>
        </body>
        </html>
        <?php
    }
}
