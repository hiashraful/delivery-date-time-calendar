// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'enqueue_delivery_slots_assets');
function enqueue_delivery_slots_assets() {
    if (is_checkout()) {
        wp_enqueue_style('delivery-slots-calendar', get_stylesheet_directory_uri() . '/css/delivery-slots.css');
        wp_enqueue_script('delivery-slots-calendar', get_stylesheet_directory_uri() . '/js/delivery-slots.js', array('jquery'), '1.0', true);
        
        // Format slots data for frontend
        $saved_slots = get_option('delivery_slots_by_date', array());
        $formatted_slots = array();
        
        foreach ($saved_slots as $date => $slots) {
            $formatted_slots[$date] = array_map(function($slot) {
                return array(
                    'start' => $slot['start'],
                    'end' => $slot['end'],
                    'display' => date('g:i a', strtotime($slot['start'])) .'<br>'. date('g:i a', strtotime($slot['end']))
                );
            }, $slots);
        }
        
        wp_localize_script('delivery-slots-calendar', 'deliverySlotsData', array(
            'slots' => $formatted_slots
        ));
    }
}

// Add delivery slots admin menu
add_action('admin_menu', 'add_delivery_slots_menu');
function add_delivery_slots_menu() {
    add_submenu_page(
        'woocommerce',
        'Set Delivery Slots',
        'Delivery Slots',
        'manage_options',
        'delivery-slots',
        'delivery_slots_page_content'
    );
}

// Delivery slots admin page content
function delivery_slots_page_content() {
    $theme_url = get_stylesheet_directory_uri();
    
    wp_enqueue_style('delivery-slots-admin', $theme_url . '/css/delivery-slots-admin.css');
    wp_enqueue_script('delivery-slots-admin', $theme_url . '/js/delivery-slots-admin.js', array('jquery'), '1.0', true);
    
    $saved_slots = get_option('delivery_slots_by_date', array());
    wp_localize_script('delivery-slots-admin', 'deliverySlotsData', array(
        'saved_slots' => $saved_slots,
        'ajax_url' => admin_url('admin-ajax.php')
    ));
    ?>
    <div class="wrap delivery-slots-admin">
        <h1>Set Delivery Slots</h1>
        
        <div class="calendar-container">
            <div id="admin-delivery-calendar"></div>
        </div>
        
        <div id="timeSlotModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h3 id="modalDateTitle">Add Time Slots for <span id="modalDate"></span></h3>
                <div id="timeSlotsContainer"></div>
                <div class="time-slot-input">
                    <input type="time" id="newSlotStart" placeholder="Start time">
                    <span>to</span>
                    <input type="time" id="newSlotEnd" placeholder="End time">
                    <button id="addNewSlotBtn" class="button">Add Slot</button>
                </div>
                <button id="saveTimeSlotsBtn" class="button button-primary">Save Slots</button>
            </div>
        </div>
    </div>
    <?php
}

// Save delivery slots via AJAX
add_action('wp_ajax_save_delivery_slots', 'save_delivery_slots');
function save_delivery_slots() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $date = sanitize_text_field($_POST['date']);
    $slots = isset($_POST['slots']) ? $_POST['slots'] : array();
    
    $sanitized_slots = array();
    foreach ($slots as $slot) {
        $sanitized_slots[] = array(
            'start' => sanitize_text_field($slot['start']),
            'end' => sanitize_text_field($slot['end']),
            'display' => sanitize_text_field($slot['start'] . ' - ' . $slot['end'])
        );
    }
    
    $existing_slots = get_option('delivery_slots_by_date', array());
    
    if (!empty($sanitized_slots)) {
        $existing_slots[$date] = $sanitized_slots;
    } else {
        unset($existing_slots[$date]);
    }
    
    update_option('delivery_slots_by_date', $existing_slots);
    
    wp_send_json_success(array(
        'date' => $date,
        'slots' => $sanitized_slots
    ));
}

// Add calendar to checkout
add_action('woocommerce_before_order_notes', 'add_delivery_slot_selection');
function add_delivery_slot_selection($checkout) {
    echo '<div id="delivery-slot-selection">';
    echo '<h3>' . __('Delivery Date & Time') . '</h3>';
    echo '<div id="delivery-calendar"></div>';
    
    woocommerce_form_field('delivery_slot', array(
        'type' => 'hidden',
        'class' => array('delivery-slot-field'),
        'required' => true,
    ), $checkout->get_value('delivery_slot'));
    
    echo '</div>';
}

// Save delivery slot to order
if (!function_exists('save_delivery_slot_to_order')) {
    add_action('woocommerce_checkout_create_order', 'save_delivery_slot_to_order', 10, 2);
    function save_delivery_slot_to_order($order, $data) {
        if (!empty($_POST['delivery_slot'])) {
            $slot_data = array(
                'display' => sanitize_text_field($_POST['delivery_slot']),
                'date' => isset($_POST['delivery_slot_date']) ? sanitize_text_field($_POST['delivery_slot_date']) : '',
                'time' => isset($_POST['delivery_slot_time']) ? sanitize_text_field($_POST['delivery_slot_time']) : ''
            );
            
            $order->update_meta_data('_delivery_slot', json_encode($slot_data));
            $order->update_meta_data('_delivery_slot_display', sanitize_text_field($_POST['delivery_slot']));
        }
    }
}

// Display selected slot on checkout
add_action('woocommerce_after_order_notes', 'display_selected_delivery_slot', 10);
function display_selected_delivery_slot($checkout) {
    $selected_slot = $checkout->get_value('delivery_slot');
    if ($selected_slot) {
        echo '<div id="selected-delivery-slot" style="margin: 15px 0; padding: 10px; background: #f8f8f8; border-radius: 4px;">';
        echo '<strong>Selected Delivery Slot:</strong> ' . esc_html($selected_slot);
        echo '</div>';
    }
}

// Display delivery slot in admin order view
add_action('woocommerce_admin_order_data_after_billing_address', 'display_delivery_slot_in_admin', 10, 1);
function display_delivery_slot_in_admin($order) {
    $delivery_slot = $order->get_meta('_delivery_slot_display');
    if (!$delivery_slot) {
        $slot_data = json_decode($order->get_meta('_delivery_slot'), true);
        $delivery_slot = $slot_data['display'] ?? '';
    }
    
    if ($delivery_slot) {
        echo '<p><strong>Delivery Slot:</strong> ' . esc_html($delivery_slot) . '</p>';
    }
}

// Add delivery slot to order emails
add_filter('woocommerce_email_order_meta_fields', 'add_delivery_slot_to_emails', 10, 3);
function add_delivery_slot_to_emails($fields, $sent_to_admin, $order) {
    $delivery_slot = $order->get_meta('_delivery_slot_display');
    if (!$delivery_slot) {
        $slot_data = json_decode($order->get_meta('_delivery_slot'), true);
        $delivery_slot = $slot_data['display'] ?? '';
    }
    
    if ($delivery_slot) {
        $fields['delivery_slot'] = array(
            'label' => __('Delivery Slot'),
            'value' => $delivery_slot,
        );
    }
    return $fields;
}
