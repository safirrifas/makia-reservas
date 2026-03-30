<?php
/**
 * Clase para gestionar el enlace a la PWA en el panel del plugin
 * Proporciona acceso directo a la aplicación móvil desde el panel de administración
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MakIA_PWA_Link {
	/**
	 * URL de la aplicación PWA
	 */
	private $pwa_url = 'https://contacpro.app/operarios';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_pwa_menu' ), 15 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_pwa_assets' ) );
	}

	/**
	 * Agregar menú de acceso a la PWA
	 */
	public function add_pwa_menu() {
		// Agregar submenú bajo MakIA Restaurante
		add_submenu_page(
			'makia',
			__( 'Panel Móvil', 'makia-reservas' ),
			__( '📱 Panel Móvil', 'makia-reservas' ),
			'makia_manage_bookings',
			'makia-pwa',
			array( $this, 'render_pwa_page' )
		);
	}

	/**
	 * Renderizar página de acceso a la PWA
	 */
	public function render_pwa_page() {
		$operator = wp_get_current_user();
		$qr_code_url = $this->generate_qr_code( $operator->user_email );
		?>
		<div class="wrap makia-pwa-wrap">
			<h1><?php esc_html_e( 'MakIA Panel Móvil', 'makia-reservas' ); ?></h1>
			
			<div class="makia-pwa-container">
				<!-- Sección de bienvenida -->
				<div class="makia-pwa-welcome">
					<h2><?php esc_html_e( 'Accede desde tu Móvil o PDA', 'makia-reservas' ); ?></h2>
					<p><?php esc_html_e( 'Usa la aplicación móvil optimizada para gestionar reservas en tiempo real desde cualquier dispositivo.', 'makia-reservas' ); ?></p>
				</div>

				<div class="makia-pwa-content">
					<!-- Columna izquierda: Instrucciones -->
					<div class="makia-pwa-instructions">
						<h3><?php esc_html_e( 'Cómo Instalar', 'makia-reservas' ); ?></h3>
						
						<div class="makia-pwa-step">
							<div class="step-number">1</div>
							<div class="step-content">
								<h4><?php esc_html_e( 'Abre el Enlace', 'makia-reservas' ); ?></h4>
								<p><?php esc_html_e( 'Haz clic en el botón "Abrir Panel Móvil" o escanea el código QR con tu dispositivo.', 'makia-reservas' ); ?></p>
							</div>
						</div>

						<div class="makia-pwa-step">
							<div class="step-number">2</div>
							<div class="step-content">
								<h4><?php esc_html_e( 'Inicia Sesión', 'makia-reservas' ); ?></h4>
								<p><?php esc_html_e( 'Usa tu email y contraseña de operario para acceder.', 'makia-reservas' ); ?></p>
							</div>
						</div>

						<div class="makia-pwa-step">
							<div class="step-number">3</div>
							<div class="step-content">
								<h4><?php esc_html_e( 'Instalar en el Móvil', 'makia-reservas' ); ?></h4>
								<p><?php esc_html_e( 'En tu navegador, busca la opción "Instalar aplicación" o "Añadir a pantalla de inicio".', 'makia-reservas' ); ?></p>
							</div>
						</div>

						<div class="makia-pwa-step">
							<div class="step-number">4</div>
							<div class="step-content">
								<h4><?php esc_html_e( '¡Listo!', 'makia-reservas' ); ?></h4>
								<p><?php esc_html_e( 'Abre la app desde tu pantalla de inicio. Funciona sin conexión.', 'makia-reservas' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Columna derecha: Botones y QR -->
					<div class="makia-pwa-actions">
						<div class="makia-pwa-button-group">
							<a href="<?php echo esc_url( $this->pwa_url ); ?>" target="_blank" class="button button-primary button-large makia-pwa-button">
								📱 <?php esc_html_e( 'Abrir Panel Móvil', 'makia-reservas' ); ?>
							</a>
							<p class="makia-pwa-button-desc">
								<?php esc_html_e( 'Se abrirá en una nueva ventana', 'makia-reservas' ); ?>
							</p>
						</div>

						<div class="makia-pwa-qr">
							<h4><?php esc_html_e( 'O escanea el código QR', 'makia-reservas' ); ?></h4>
							<img src="<?php echo esc_url( $qr_code_url ); ?>" alt="QR Code" class="makia-pwa-qr-image">
							<p class="makia-pwa-qr-desc">
								<?php esc_html_e( 'Escanea con tu móvil para acceder directamente', 'makia-reservas' ); ?>
							</p>
						</div>

						<div class="makia-pwa-info">
							<h4><?php esc_html_e( 'Información de tu Cuenta', 'makia-reservas' ); ?></h4>
							<ul>
								<li><strong><?php esc_html_e( 'Email:', 'makia-reservas' ); ?></strong> <?php echo esc_html( $operator->user_email ); ?></li>
								<li><strong><?php esc_html_e( 'Nombre:', 'makia-reservas' ); ?></strong> <?php echo esc_html( $operator->display_name ); ?></li>
								<li><strong><?php esc_html_e( 'Rol:', 'makia-reservas' ); ?></strong> <?php echo esc_html( implode( ', ', $operator->roles ) ); ?></li>
							</ul>
						</div>
					</div>
				</div>

				<!-- Sección de características -->
				<div class="makia-pwa-features">
					<h3><?php esc_html_e( 'Características de la App', 'makia-reservas' ); ?></h3>
					<div class="features-grid">
						<div class="feature-card">
							<div class="feature-icon">📅</div>
							<h4><?php esc_html_e( 'Reservas del Día', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Ve todas las reservas del día en tiempo real', 'makia-reservas' ); ?></p>
						</div>
						<div class="feature-card">
							<div class="feature-icon">🔍</div>
							<h4><?php esc_html_e( 'Búsqueda Rápida', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Busca clientes por nombre, email o teléfono', 'makia-reservas' ); ?></p>
						</div>
						<div class="feature-card">
							<div class="feature-icon">📝</div>
							<h4><?php esc_html_e( 'Notas Internas', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Añade y consulta notas sobre cada reserva', 'makia-reservas' ); ?></p>
						</div>
						<div class="feature-card">
							<div class="feature-icon">🗺️</div>
							<h4><?php esc_html_e( 'Mapa de Clientes', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Visualiza ubicaciones de clientes en el mapa', 'makia-reservas' ); ?></p>
						</div>
						<div class="feature-card">
							<div class="feature-icon">📱</div>
							<h4><?php esc_html_e( 'Funciona Offline', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Accede a los datos sin conexión a internet', 'makia-reservas' ); ?></p>
						</div>
						<div class="feature-card">
							<div class="feature-icon">🔔</div>
							<h4><?php esc_html_e( 'Notificaciones Push', 'makia-reservas' ); ?></h4>
							<p><?php esc_html_e( 'Recibe alertas de nuevas reservas al instante', 'makia-reservas' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Sección de soporte -->
				<div class="makia-pwa-support">
					<h3><?php esc_html_e( 'Necesitas Ayuda?', 'makia-reservas' ); ?></h3>
					<p>
						<?php esc_html_e( 'Si tienes problemas para instalar o usar la aplicación, contacta con el administrador del sitio.', 'makia-reservas' ); ?>
					</p>
					<ul>
						<li><?php esc_html_e( 'Asegúrate de tener una conexión a internet estable', 'makia-reservas' ); ?></li>
						<li><?php esc_html_e( 'Usa un navegador moderno (Chrome, Firefox, Safari, Edge)', 'makia-reservas' ); ?></li>
						<li><?php esc_html_e( 'Borra el caché del navegador si tienes problemas', 'makia-reservas' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Generar código QR para la PWA
	 */
	private function generate_qr_code( $email ) {
		$pwa_link = add_query_arg( 'email', urlencode( $email ), $this->pwa_url );
		$qr_api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=';
		return $qr_api . urlencode( $pwa_link );
	}

	/**
	 * Enqueue CSS y JS para la página de PWA
	 */
	public function enqueue_pwa_assets( $hook ) {
		if ( 'makia-reservas_page_makia-pwa' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'makia-pwa-style',
			MAKIA_PLUGIN_URL . 'assets/css/makia-pwa.css',
			array(),
			MAKIA_VERSION
		);
	}
}

// Instanciar la clase
new MakIA_PWA_Link();
