<?php
/**
 * Plugin Name: Boas-Vindas Prado Aqui
 * Plugin URI: https://boasvindas.pradoaqui.com.br
 * Description: Sistema de entrega automática de guias digitais personalizados para hóspedes de temporada.
 * Version: 1.0.0
 * Author: Prado Aqui
 * Author URI: https://pradoaqui.com.br
 * License: GPL2
 * Text Domain: prado-welcome
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Paths
define('PRADO_WELCOME_PATH', plugin_dir_path(__FILE__));
define('PRADO_WELCOME_URL', plugin_dir_url(__FILE__));

// Include Plugin Files
require_once PRADO_WELCOME_PATH . 'includes/class-database.php';
require_once PRADO_WELCOME_PATH . 'includes/class-wc-integration.php';
require_once PRADO_WELCOME_PATH . 'includes/class-rest-api.php';
require_once PRADO_WELCOME_PATH . 'includes/class-guest-view.php';

// Plugin Activation Hook
register_activation_hook(__FILE__, 'prado_welcome_activate_plugin');
function prado_welcome_activate_plugin() {
    Prado_Welcome_Database::create_tables();
}

// Initialize Plugin Subsystems
Prado_Welcome_WC_Integration::init();
Prado_Welcome_REST_API::init();
Prado_Welcome_Guest_View::init();

// Register WordPress Admin Menu Page
add_action('admin_menu', 'prado_welcome_register_menu');
function prado_welcome_register_menu() {
    add_menu_page(
        'Boas-Vindas', 
        'Boas-Vindas', 
        'read', 
        'prado-welcome', 
        'prado_welcome_render_admin_spa_wrapper', 
        'dashicons-welcome-widgets-menus', 
        26
    );
}

// Enqueue scripts and styles in Admin Panel
add_action('admin_enqueue_scripts', 'prado_welcome_enqueue_admin_assets');
function prado_welcome_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_prado-welcome') {
        return;
    }

    // Google Font: Outfit
    wp_enqueue_style('prado-welcome-google-fonts', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap', array(), null);

    // FontAwesome Icons
    wp_enqueue_style('prado-welcome-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

    // Admin CSS
    wp_enqueue_style('prado-welcome-admin-css', PRADO_WELCOME_URL . 'assets/css/admin-spa.css', array(), '1.0.0');

    // Media Library Uploader Dependencies
    wp_enqueue_media();

    // QR Code Vendor Script
    wp_enqueue_script('prado-welcome-qrcode-vendor', PRADO_WELCOME_URL . 'assets/js/vendor/qrcode.min.js', array(), '1.0.0', true);

    // Admin SPA JS
    wp_enqueue_script('prado-welcome-admin-spa', PRADO_WELCOME_URL . 'assets/js/admin-spa.js', array('jquery'), '1.0.0', true);

    // Localize Data for Rest API calls
    wp_localize_script('prado-welcome-admin-spa', 'pradoWelcomeData', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}

// Render Admin SPA Page Wrapper
function prado_welcome_render_admin_spa_wrapper() {
    $assets_url = PRADO_WELCOME_URL . 'assets/';
    ?>
    <div class="prado-welcome-app">
        
        <!-- App Header Banner -->
        <header class="app-header">
            <div class="app-brand">
                <div class="app-logo-box"><i class="fa-solid fa-hotel"></i></div>
                <div class="app-title-area">
                    <h1>Boas-Vindas Prado Aqui</h1>
                    <p>Guia Digital Inteligente para seus Hóspedes</p>
                </div>
            </div>
            
            <div class="user-welcome-badge">
                <span>Olá, <strong><?php echo esc_html(wp_get_current_user()->display_name); ?></strong> <span class="wave-emoji">👋</span></span>
            </div>
        </header>

        <!-- Main Workspace Layout -->
        <div class="app-layout">
            
            <!-- Sidebar Navigation Menu -->
            <aside class="app-sidebar">
                <button class="menu-item active" data-view="dashboard">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard
                </button>
                <button class="menu-item" data-view="properties">
                    <i class="fa-solid fa-house-chimney"></i> Meus Imóveis
                </button>
                <button class="menu-item" data-view="guests">
                    <i class="fa-solid fa-users"></i> Hóspedes
                </button>
                <button class="menu-item" data-view="reservations">
                    <i class="fa-solid fa-calendar-check"></i> Reservas
                </button>
                <button class="menu-item" data-view="content">
                    <i class="fa-solid fa-book-open"></i> Conteúdo
                </button>
                <button class="menu-item" data-view="subscription">
                    <i class="fa-solid fa-credit-card"></i> Minha Assinatura
                </button>
            </aside>

            <!-- Views Switcher Area -->
            <main class="app-content">

                <!-- 1. VIEW: DASHBOARD -->
                <section class="app-view active" id="view-dashboard">
                    <div class="stats-grid">
                        <div class="stat-card blue">
                            <div class="stat-header">
                                <span>Meus Imóveis</span>
                                <i class="fa-solid fa-hotel"></i>
                            </div>
                            <div class="stat-value" id="stat-properties">0</div>
                        </div>
                        <div class="stat-card emerald">
                            <div class="stat-header">
                                <span>Reservas Ativas</span>
                                <i class="fa-solid fa-calendar-day"></i>
                            </div>
                            <div class="stat-value" id="stat-reservations">0</div>
                        </div>
                        <div class="stat-card pink">
                            <div class="stat-header">
                                <span>Guias Ativos</span>
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <div class="stat-value" id="stat-active-guides">0</div>
                        </div>
                        <div class="stat-card slate">
                            <div class="stat-header">
                                <span>Guias Expirados</span>
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <div class="stat-value" id="stat-expired-guides">0</div>
                        </div>
                    </div>

                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h2><i class="fa-solid fa-clock"></i> Próximos Hóspedes</h2>
                            <button class="btn-admin btn-admin-primary btn-sm" onclick="openAddReservationModal()"><i class="fa-solid fa-plus"></i> Nova Reserva</button>
                        </div>
                        <div class="table-responsive">
                            <table class="prado-table">
                                <thead>
                                    <tr>
                                        <th>Hóspede</th>
                                        <th>Imóvel</th>
                                        <th>Período</th>
                                        <th>Status do Guia</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="upcoming-guests-list">
                                    <!-- Rendered dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 2. VIEW: PROPERTIES -->
                <section class="app-view" id="view-properties">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="margin:0; font-size:20px; font-weight:700;"><i class="fa-solid fa-hotel" style="color:var(--color-primary);"></i> Meus Imóveis</h2>
                        <button class="btn-admin btn-admin-primary" onclick="openAddPropertyModal()"><i class="fa-solid fa-plus"></i> Novo Imóvel</button>
                    </div>
                    <div class="stats-grid" id="properties-grid-list" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                        <!-- Rendered dynamically -->
                    </div>
                </section>

                <!-- 3. VIEW: GUESTS -->
                <section class="app-view" id="view-guests">
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h2><i class="fa-solid fa-users"></i> Hóspedes Cadastrados</h2>
                            <button class="btn-admin btn-admin-primary btn-sm" onclick="openAddGuestModal()"><i class="fa-solid fa-plus"></i> Novo Hóspede</button>
                        </div>
                        <div class="table-responsive">
                            <table class="prado-table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>WhatsApp / Contato</th>
                                        <th>Pessoas</th>
                                        <th>Observações</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="guests-table-body">
                                    <!-- Rendered dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 4. VIEW: RESERVATIONS -->
                <section class="app-view" id="view-reservations">
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h2><i class="fa-solid fa-calendar-check"></i> Registro de Reservas</h2>
                            <button class="btn-admin btn-admin-primary btn-sm" onclick="openAddReservationModal()"><i class="fa-solid fa-plus"></i> + Nova Reserva</button>
                        </div>
                        <div class="table-responsive">
                            <table class="prado-table">
                                <thead>
                                    <tr>
                                        <th>Hóspede</th>
                                        <th>Imóvel</th>
                                        <th>Período</th>
                                        <th>Status do Guia</th>
                                        <th>Controle do Guia</th>
                                    </tr>
                                </thead>
                                <tbody id="reservations-table-body">
                                    <!-- Rendered dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- 5. VIEW: CONTENT MANAGER -->
                <section class="app-view" id="view-content">
                    <div class="panel-card">
                        <div class="form-group">
                            <label class="form-label" style="font-size:14px;">Imóvel Selecionado</label>
                            <select class="form-control" id="content-property-select" onchange="selectPropertyForContent(this.value)">
                                <!-- Properties select dynamic dropdown options -->
                            </select>
                        </div>
                    </div>

                    <div id="content-builder-area" style="display:none;">
                        
                        <!-- Content sub menu selection -->
                        <div class="content-sections-tabs">
                            <button class="content-sec-tab active" data-section="welcome"><i class="fa-solid fa-handshake"></i> Boas-Vindas</button>
                            <button class="content-sec-tab" data-section="checkin"><i class="fa-solid fa-key"></i> Check-in</button>
                            <button class="content-sec-tab" data-section="wifi"><i class="fa-solid fa-wifi"></i> Wi-Fi</button>
                            <button class="content-sec-tab" data-section="structure"><i class="fa-solid fa-circle-info"></i> Estrutura</button>
                            <button class="content-sec-tab" data-section="how_to_use"><i class="fa-solid fa-sliders"></i> Como Usar</button>
                            <button class="content-sec-tab" data-section="rules"><i class="fa-solid fa-triangle-exclamation"></i> Regras</button>
                            <button class="content-sec-tab" data-section="contacts"><i class="fa-solid fa-address-book"></i> Contatos</button>
                            <button class="content-sec-tab" data-section="places"><i class="fa-solid fa-map-location-dot"></i> Turismo local</button>
                            <button class="content-sec-tab" data-section="checkout"><i class="fa-solid fa-door-open"></i> Checkout</button>
                        </div>

                        <!-- 5a. Welcome Msg section -->
                        <div class="panel-card content-section-panel active" id="sec-panel-welcome">
                            <h3>Boas-Vindas do Imóvel</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="form-group">
                                    <label class="form-label">Título da Mensagem</label>
                                    <input type="text" class="form-control" id="welcome-title" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Mensagem de Boas-Vindas</label>
                                    <textarea class="form-control" id="welcome-msg" rows="5" placeholder="Olá, preparamos tudo com carinho para que aproveite sua estadia..."></textarea>
                                </div>
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Nome do Anfitrião</label>
                                        <input type="text" class="form-control" id="welcome-hostname" />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Assinatura</label>
                                        <input type="text" class="form-control" id="welcome-signature" placeholder="Ex: Família Prado" />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Foto do Anfitrião</label>
                                    <div class="media-uploader-box">
                                        <img src="" id="welcome-host-photo-preview" class="media-preview-img" style="display:none;" />
                                        <input type="text" class="form-control" id="welcome-host-photo" placeholder="URL da foto" />
                                        <button type="button" class="btn-admin btn-admin-secondary wp-media-trigger" data-target="welcome-host-photo">Selecionar da Biblioteca</button>
                                    </div>
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Boas-Vindas</button>
                            </form>
                        </div>

                        <!-- 5b. Check-in Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-checkin">
                            <h3>Instruções de Check-in</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Horário de Entrada (Check-in)</label>
                                        <input type="text" class="form-control" id="checkin-time" placeholder="Ex: 14:00" />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Fechadura Digital?</label>
                                        <select class="form-control" id="checkin-digital">
                                            <option value="no">Não (Acesso por chaves convencionais)</option>
                                            <option value="yes">Sim (Código de acesso digital)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Código de Acesso / Chave</label>
                                    <input type="text" class="form-control" id="checkin-code" placeholder="Ex: Senha ou instruções de recebimento de chaves" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instruções de Acesso Gerais</label>
                                    <textarea class="form-control" id="checkin-instructions" rows="4" placeholder="Ex: A chave está na caixinha com segredo na lateral da porta de entrada..."></textarea>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Acesso ao Portão</label>
                                    <input type="text" class="form-control" id="checkin-gate" placeholder="Ex: Use o controle remoto preto" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Estacionamento / Garagem</label>
                                    <input type="text" class="form-control" id="checkin-parking" placeholder="Ex: Vaga livre 12 na garagem externa" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ponto de Referência</label>
                                    <input type="text" class="form-control" id="checkin-ref" placeholder="Ex: Esquina com a padaria Prado" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Outras Instruções</label>
                                    <textarea class="form-control" id="checkin-other" rows="3" placeholder="Informações de luzes, chaves disjuntoras adicionais..."></textarea>
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Check-in</button>
                            </form>
                        </div>

                        <!-- 5c. Wi-Fi Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-wifi">
                            <h3>Instruções de Internet Wi-Fi</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Nome da Rede (SSID)</label>
                                        <input type="text" class="form-control" id="wifi-ssid-field" placeholder="Ex: Rancho_Reserva_Prado" />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Senha da Rede</label>
                                        <input type="text" class="form-control" id="wifi-pass-field" placeholder="Ex: prado12345" />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Observações adicionais</label>
                                    <input type="text" class="form-control" id="wifi-notes-field" placeholder="Ex: Rede 5G disponível na área gourmet" />
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Wi-Fi</button>
                            </form>
                        </div>

                        <!-- 5d. Structure Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-structure">
                            <h3>Estrutura e Comodidades</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <p style="font-size:13px; color:var(--text-secondary); margin-bottom:20px;">Selecione os itens existentes na hospedagem:</p>
                                <div class="structure-checkbox-grid">
                                    <?php
                                    $structs = array(
                                        'piscina' => 'Piscina',
                                        'churrasqueira' => 'Churrasqueira',
                                        'wi_fi' => 'Wi-Fi',
                                        'tv' => 'TV',
                                        'netflix' => 'Netflix / Streaming',
                                        'ar_condicionado' => 'Ar-condicionado',
                                        'fogao' => 'Fogão',
                                        'cooktop' => 'Cooktop',
                                        'forno' => 'Forno',
                                        'freezer' => 'Freezer',
                                        'maquina_lavar' => 'Máquina de lavar',
                                        'sauna' => 'Sauna',
                                        'pesqueiro' => 'Pesqueiro',
                                        'acesso_rio' => 'Acesso ao Rio',
                                        'barco' => 'Barco',
                                        'area_gourmet' => 'Área Gourmet',
                                        'jacuzzi' => 'Jacuzzi',
                                        'lareira' => 'Lareira'
                                    );
                                    foreach ($structs as $key => $label): ?>
                                        <label class="structure-checkbox-item">
                                            <input type="checkbox" name="prop_structures" value="<?php echo esc_attr($key); ?>">
                                            <span><?php echo esc_html($label); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary" style="margin-top:20px;"><i class="fa-solid fa-save"></i> Salvar Comodidades</button>
                            </form>
                        </div>

                        <!-- 5e. How to use section -->
                        <div class="panel-card content-section-panel" id="sec-panel-how_to_use">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                                <h3>Como Usar Equipamentos</h3>
                                <button type="button" class="btn-admin btn-admin-secondary btn-sm" onclick="addHowToUseItem()"><i class="fa-solid fa-plus"></i> Adicionar Item</button>
                            </div>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="builder-items-list" id="how-to-use-builder-list">
                                    <!-- Dynamic builder list -->
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Instruções</button>
                            </form>
                        </div>

                        <!-- 5f. Rules Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-rules">
                            <h3>Regras da Casa</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="form-group">
                                    <label class="form-label">Animais de Estimação (Pets)</label>
                                    <input type="text" class="form-control" id="rule-pet" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Fumo (Cigarro / Narguilé)</label>
                                    <input type="text" class="form-control" id="rule-smoke" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Festas e Eventos</label>
                                    <input type="text" class="form-control" id="rule-parties" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Visitas</label>
                                    <input type="text" class="form-control" id="rule-visitors" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Horário de Silêncio</label>
                                    <input type="text" class="form-control" id="rule-silence" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Quantidade Máxima de Hóspedes</label>
                                    <input type="text" class="form-control" id="rule-max-guests" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Regras da Piscina</label>
                                    <input type="text" class="form-control" id="rule-pool" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Outras Regras / Cuidados Gerais</label>
                                    <textarea class="form-control" id="rule-custom" rows="3" placeholder="Ex: Cuidado ao manusear as espreguiçadeiras..."></textarea>
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Regras</button>
                            </form>
                        </div>

                        <!-- 5g. Contacts Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-contacts">
                            <h3>Contatos e Emergências</h3>
                            <div style="display:grid; grid-template-columns:1fr 1.5fr; gap:24px;">
                                <div>
                                    <h4>Novo Contato</h4>
                                    <form id="form-add-contact" onsubmit="addContact(event)">
                                        <div class="form-group">
                                            <label class="form-label">Nome do Contato</label>
                                            <input type="text" class="form-control" id="contact-name" placeholder="Ex: Hospital Municipal" required />
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" class="form-control" id="contact-phone" placeholder="Ex: (17) 99999-9999" required />
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Tipo de Contato</label>
                                            <select class="form-control" id="contact-type">
                                                <option value="hospital">Hospital / Pronto Socorro</option>
                                                <option value="policia">Polícia</option>
                                                <option value="bombeiros">Bombeiros</option>
                                                <option value="chaveiro">Chaveiro</option>
                                                <option value="posto">Posto de Combustível</option>
                                                <option value="mercado">Supermercado</option>
                                                <option value="suporte">Suporte Técnico / Limpeza</option>
                                                <option value="outros">Outros</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Observações</label>
                                            <input type="text" class="form-control" id="contact-notes" placeholder="Ex: Aberto 24h" />
                                        </div>
                                        <button type="submit" class="btn-admin btn-admin-primary btn-block"><i class="fa-solid fa-plus"></i> Adicionar</button>
                                    </form>
                                </div>
                                <div>
                                    <h4>Lista de Contatos Cadastrados</h4>
                                    <div id="contacts-builder-list" style="margin-top:10px;">
                                        <!-- Dynamic load list -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5h. Local places Section -->
                        <div class="panel-card content-section-panel" id="sec-panel-places">
                            <h3>Recomendações Locais (Turismo / Serviços)</h3>
                            <div style="display:grid; grid-template-columns:1fr 1.5fr; gap:24px;">
                                <div>
                                    <h4>Novo Local Recomendado</h4>
                                    <form id="form-add-place" onsubmit="addPlace(event)">
                                        <div class="form-group-row">
                                            <div class="form-group">
                                                <label class="form-label">Nome do Local</label>
                                                <input type="text" class="form-control" id="place-name" placeholder="Ex: Cachoeira do Prado" required />
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Tipo / Categoria</label>
                                                <select class="form-control" id="place-type">
                                                    <option value="restaurante">Restaurante / Bar</option>
                                                    <option value="passeio">Passeio / Trilha</option>
                                                    <option value="turismo">Ponto Turístico</option>
                                                    <option value="compras">Compras / Lembranças</option>
                                                    <option value="pesca">Rio / Pesca</option>
                                                    <option value="mercado">Supermercado / Padaria</option>
                                                    <option value="posto">Posto de Combustível</option>
                                                    <option value="experiencia">Experiência Recomendada</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Descrição</label>
                                            <input type="text" class="form-control" id="place-desc" placeholder="Ex: Excelente comida mineira à beira rio." />
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Imagem de Capa</label>
                                            <div class="media-uploader-box">
                                                <img src="" id="place-image-preview" class="media-preview-img" style="display:none;" />
                                                <input type="text" class="form-control" id="place-image" placeholder="URL da foto" />
                                                <button type="button" class="btn-admin btn-admin-secondary wp-media-trigger" data-target="place-image">Selecionar</button>
                                            </div>
                                        </div>
                                        <div class="form-group-row">
                                            <div class="form-group">
                                                <label class="form-label">Endereço</label>
                                                <input type="text" class="form-control" id="place-address" placeholder="Av. Principal, 100" />
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Telefone comercial</label>
                                                <input type="text" class="form-control" id="place-phone" placeholder="(17) 3333-3333" />
                                            </div>
                                        </div>
                                        <div class="form-group-row">
                                            <div class="form-group">
                                                <label class="form-label">Link do Site / Rede Social</label>
                                                <input type="text" class="form-control" id="place-link" placeholder="http://..." />
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Google Maps Link</label>
                                                <input type="text" class="form-control" id="place-location" placeholder="http://maps.google..." />
                                            </div>
                                        </div>
                                        <button type="submit" class="btn-admin btn-admin-primary btn-block"><i class="fa-solid fa-plus"></i> Adicionar Recomendação</button>
                                    </form>
                                </div>
                                <div>
                                    <h4>Locais Cadastrados</h4>
                                    <div id="places-builder-list" style="margin-top:10px;">
                                        <!-- Dynamic load list -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5i. Checkout checklist section -->
                        <div class="panel-card content-section-panel" id="sec-panel-checkout">
                            <h3>Procedimentos de Saída (Checkout)</h3>
                            <form onsubmit="saveActiveContent(event)">
                                <div class="form-group">
                                    <label class="form-label">Horário Limite de Saída (Checkout)</label>
                                    <input type="text" class="form-control" id="checkout-time" placeholder="Ex: 12:00" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instrução para Chave</label>
                                    <input type="text" class="form-control" id="checkout-key" placeholder="Ex: Deixar chave na caixinha da fechadura digital." />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instrução para Lixo</label>
                                    <input type="text" class="form-control" id="checkout-trash" placeholder="Ex: Recolher e depositar nas caçambas da rua." />
                                </div>
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Luzes</label>
                                        <input type="text" class="form-control" id="checkout-lights" placeholder="Ex: Desligar todas as luzes." />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Ar-condicionado</label>
                                        <input type="text" class="form-control" id="checkout-ac" placeholder="Ex: Certificar-se de desligar todos os AC." />
                                    </div>
                                </div>
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Portas</label>
                                        <input type="text" class="form-control" id="checkout-doors" placeholder="Ex: Trancar todas as portas de saída." />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Janelas</label>
                                        <input type="text" class="form-control" id="checkout-windows" placeholder="Ex: Verificar se todas estão trancadas." />
                                    </div>
                                </div>
                                <div class="form-group-row">
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Louça</label>
                                        <input type="text" class="form-control" id="checkout-dishes" placeholder="Ex: Lavar e guardar a louça utilizada." />
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Instrução para Objetos Pessoais</label>
                                        <input type="text" class="form-control" id="checkout-objects" placeholder="Ex: Verificar tomadas e gavetas." />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Aviso de Saída / Mensagem de encerramento</label>
                                    <textarea class="form-control" id="checkout-notice" rows="3" placeholder="Ao sair, clique no botão Notificar Saída para avisar o anfitrião no WhatsApp."></textarea>
                                </div>
                                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Salvar Checkout</button>
                            </form>
                        </div>

                    </div>
                </section>

                <!-- 6. VIEW: SUBSCRIPTION DETAILS -->
                <section class="app-view" id="view-subscription">
                    <div class="panel-card" id="subscription-details-panel">
                        <!-- Loaded dynamically -->
                    </div>
                </section>

            </main>
        </div>

        <!-- ============================================== -->
        <!--                  MODAL: PROPERTY               -->
        <!-- ============================================== -->
        <div class="prado-modal" id="modal-property">
            <div class="modal-overlay"></div>
            <form onsubmit="saveProperty(event)" class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-property-title">Cadastrar Novo Imóvel</h3>
                    <button class="btn-modal-close" type="button"><i class="fa-solid fa-xmark"></i></button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" id="property-id-field" value="" />
                        
                        <div class="form-group">
                            <label class="form-label">Nome do Imóvel</label>
                            <input type="text" class="form-control" id="prop-name" placeholder="Ex: Rancho Reserva Prado" required />
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="prop-city" placeholder="Ex: Prado" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <input type="text" class="form-control" id="prop-state" placeholder="Ex: BA" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Endereço Completo</label>
                            <input type="text" class="form-control" id="prop-address" placeholder="Rua das Palmeiras, Km 5" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Google Maps URL</label>
                            <input type="text" class="form-control" id="prop-location-link" placeholder="https://maps.google.com/..." />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Descrição Geral</label>
                            <textarea class="form-control" id="prop-desc" rows="3" placeholder="Breve apresentação sobre a hospedagem..."></textarea>
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label">Nome do Anfitrião</label>
                                <input type="text" class="form-control" id="prop-hostname" placeholder="Nome visível para o hóspede" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">WhatsApp de Suporte</label>
                                <input type="text" class="form-control" id="prop-host-whatsapp" placeholder="(17) 99999-9999" />
                                <input type="hidden" id="prop-host-phone" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Principal do Imóvel</label>
                            <div class="media-uploader-box">
                                <img src="" id="prop-photo-main-preview" class="media-preview-img" style="display:none;" />
                                <input type="text" class="form-control" id="prop-photo-main" placeholder="URL da foto principal" />
                                <button type="button" class="btn-admin btn-admin-secondary wp-media-trigger" data-target="prop-photo-main">Biblioteca de Mídia</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Logo do Imóvel / Marca</label>
                            <div class="media-uploader-box">
                                <img src="" id="prop-logo-preview" class="media-preview-img" style="display:none;" />
                                <input type="text" class="form-control" id="prop-logo" placeholder="URL do logotipo" />
                                <button type="button" class="btn-admin btn-admin-secondary wp-media-trigger" data-target="prop-logo">Biblioteca de Mídia</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-admin btn-admin-secondary btn-modal-cancel">Cancelar</button>
                        <button type="submit" class="btn-admin btn-admin-primary">Salvar Imóvel</button>
                    </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!--                    MODAL: GUEST                -->
        <!-- ============================================== -->
        <div class="prado-modal" id="modal-guest">
            <div class="modal-overlay"></div>
            <form onsubmit="saveGuest(event)" class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-guest-title">Cadastrar Novo Hóspede</h3>
                    <button class="btn-modal-close" type="button"><i class="fa-solid fa-xmark"></i></button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" id="guest-id-field" value="" />
                        <div class="form-group">
                            <label class="form-label">Nome do Hóspede</label>
                            <input type="text" class="form-control" id="guest-name" placeholder="Ex: João da Silva" required />
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label">Telefone (WhatsApp)</label>
                                <input type="text" class="form-control" id="guest-phone" placeholder="Ex: (17) 99999-9999" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Qtd de Hóspedes (Acompanhantes)</label>
                                <input type="number" class="form-control" id="guest-pax" min="1" value="1" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="guest-notes" rows="3" placeholder="Informações de restrições ou pedidos específicos..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-admin btn-admin-secondary btn-modal-cancel">Cancelar</button>
                        <button type="submit" class="btn-admin btn-admin-primary">Salvar Hóspede</button>
                    </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!--                 MODAL: RESERVATION             -->
        <!-- ============================================== -->
        <div class="prado-modal" id="modal-reservation">
            <div class="modal-overlay"></div>
            <form onsubmit="saveReservation(event)" class="modal-container">
                <div class="modal-header">
                    <h3 id="modal-reservation-title">Nova Reserva</h3>
                    <button class="btn-modal-close" type="button"><i class="fa-solid fa-xmark"></i></button>
                </div>
                    <div class="modal-body">
                        <input type="hidden" id="reservation-id-field" value="" />
                        
                        <div class="form-group">
                            <label class="form-label">Imóvel</label>
                            <select class="form-control" id="res-property-id" required>
                                <!-- dynamic properties list options -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nome do Hóspede</label>
                            <input type="text" class="form-control" id="res-guest-name" placeholder="Ex: João da Silva" required />
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label">WhatsApp do Hóspede</label>
                                <input type="text" class="form-control" id="res-guest-phone" placeholder="Ex: (17) 99999-9999" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantidade de Pessoas</label>
                                <input type="number" class="form-control" id="res-guest-pax" min="1" value="1" />
                            </div>
                        </div>
                        <div class="form-group-row">
                            <div class="form-group">
                                <label class="form-label">Data de Entrada (Check-in)</label>
                                <input type="date" class="form-control" id="res-checkin" required />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data de Saída (Check-out)</label>
                                <input type="date" class="form-control" id="res-checkout" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Observações da Reserva</label>
                            <textarea class="form-control" id="res-notes" rows="2" placeholder="Notas internas..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-admin btn-admin-secondary btn-modal-cancel">Cancelar</button>
                        <button type="submit" class="btn-admin btn-admin-primary">Salvar Reserva & Gerar Guia</button>
                    </div>
            </form>
        </div>

        <!-- ============================================== -->
        <!--                   MODAL: QR CODE               -->
        <!-- ============================================== -->
        <div class="prado-modal" id="modal-qrcode">
            <div class="modal-overlay"></div>
            <div class="modal-container" style="max-width:380px;">
                <div class="modal-header">
                    <h3>QR Code do Guia Digital</h3>
                    <button class="btn-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="modal-body qr-modal-content">
                    <div class="qr-code-canvas-container">
                        <div id="qr-code-canvas"></div>
                    </div>
                    <p style="font-size:13px; color:var(--text-secondary); margin-bottom:14px;">O hóspede pode escancear o código com a câmera do celular para abrir o Guia de Hospedagem.</p>
                    <div class="qr-link-text" id="qr-link-placeholder"></div>
                    
                    <div style="display:flex; gap:8px; width:100%; margin-top:14px;">
                        <button class="btn-admin btn-admin-secondary btn-sm" onclick="printQrCode()" style="flex:1; justify-content:center;"><i class="fa-solid fa-print"></i> Imprimir</button>
                        <button class="btn-admin btn-admin-primary btn-sm" onclick="downloadQrCode()" style="flex:1; justify-content:center;"><i class="fa-solid fa-download"></i> Baixar Imagem</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- App Alerts Toast -->
        <div class="admin-toast">Status salvo com sucesso!</div>

    </div>
    <?php
}
