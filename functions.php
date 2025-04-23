// 🚚 Enqueue frontend scripts/styles on checkout page
add_action('wp_enqueue_scripts', 'enqueue_delivery_slots_assets');
function enqueue_delivery_slots_assets() {
    if (!is_checkout()) return;

    $theme_url = get_stylesheet_directory_uri();

    wp_enqueue_style('delivery-slots', $theme_url . '/css/delivery-slots.css');
    wp_enqueue_script('delivery-slots-calendar', $theme_url . '/js/delivery-slots.js', ['jquery'], '1.0', true);

    $saved_slots = get_option('delivery_slots_by_date', []);
    $formatted_slots = [];

    foreach ($saved_slots as $date => $slots) {
        $formatted_slots[$date] = array_map(function ($slot) {
            return [
                'start' => $slot['start'],
                'end' => $slot['end'],
                'display' => date('g:i a', strtotime($slot['start'])) . '<br>' . date('g:i a', strtotime($slot['end']))
            ];
        }, $slots);
    }

    wp_localize_script('delivery-slots-calendar', 'deliverySlotsData', [
        'slots' => $formatted_slots
    ]);
}

// 🛠️ Admin Menu
add_action('admin_menu', 'add_delivery_slots_menu');
function add_delivery_slots_menu() {
    add_submenu_page(
        'woocommerce',
        'Set Delivery Slots',
        'Delivery Slots',
        'manage_options',
        'delivery-slots',
        'render_delivery_slots_admin_page'
    );
}

// 🖥️ Admin Page Content
function render_delivery_slots_admin_page() {
    $theme_url = get_stylesheet_directory_uri();

    wp_enqueue_style('delivery-slots-admin', $theme_url . '/css/delivery-slots-admin.css');
    wp_enqueue_script('delivery-slots-admin', $theme_url . '/js/delivery-slots-admin.js', ['jquery'], '1.0', true);

    $saved_slots = get_option('delivery_slots_by_date', []);
    wp_localize_script('delivery-slots-admin', 'deliverySlotsData', [
        'saved_slots' => $saved_slots,
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
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

// 💾 Save delivery slots via AJAX
add_action('wp_ajax_save_delivery_slots', 'save_delivery_slots');
function save_delivery_slots() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $date = sanitize_text_field($_POST['date'] ?? '');
    $slots = $_POST['slots'] ?? [];

    $sanitized_slots = array_map(function ($slot) {
        return [
            'start' => sanitize_text_field($slot['start']),
            'end' => sanitize_text_field($slot['end']),
            'display' => sanitize_text_field($slot['start'] . ' - ' . $slot['end']),
        ];
    }, $slots);

    $existing = get_option('delivery_slots_by_date', []);

    if (!empty($sanitized_slots)) {
        $existing[$date] = $sanitized_slots;
    } else {
        unset($existing[$date]);
    }

    update_option('delivery_slots_by_date', $existing);

    wp_send_json_success([
        'date' => $date,
        'slots' => $sanitized_slots
    ]);
}

// 📅 Inject calendar UI at checkout
add_action('woocommerce_before_order_notes', 'add_delivery_slot_selection');
function add_delivery_slot_selection($checkout) {
    echo '<div id="delivery-slot-section">';
    echo '<div id="delivery-slot-selection" style="display: none;">';
    echo '<h3>' . __('Delivery Date & Time') . '</h3>';
    echo '<div id="delivery-calendar"></div>';

    woocommerce_form_field('delivery_slot', [
        'type' => 'hidden',
        'class' => ['delivery-slot-field'],
        'required' => true,
    ], $checkout->get_value('delivery_slot'));

    echo '</div></div>';
}

// 📦 Watch zip code and determine slot availability
add_action('wp_enqueue_scripts', 'enqueue_zipcode_watcher');
function enqueue_zipcode_watcher() {
    if (!is_checkout()) return;

    wp_enqueue_script('zipcode-watcher', get_stylesheet_directory_uri() . '/js/zipcode-watcher.js', ['jquery'], '1.0', true);

    wp_localize_script('zipcode-watcher', 'zipcodeData', [
        'eligible_zipcodes' => get_eligible_zipcodes(),
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}

// 🔎 Get eligible zipcodes from shipping zones
function get_eligible_zipcodes() {
    $zipcodes = [];
    $zones = WC_Data_Store::load('shipping-zone')->get_zones();

    foreach ($zones as $zone_data) {
        $zone = new WC_Shipping_Zone($zone_data->zone_id);
        foreach ($zone->get_zone_locations() as $location) {
            if ($location->type !== 'postcode') continue;

            if (strpos($location->code, '*') !== false) {
                $zipcodes[] = [
                    'type' => 'wildcard',
                    'pattern' => str_replace('*', '', $location->code)
                ];
            } elseif (strpos($location->code, '...') !== false) {
                [$min, $max] = explode('...', $location->code);
                $zipcodes[] = [
                    'type' => 'range',
                    'min' => $min,
                    'max' => $max
                ];
            } else {
                $zipcodes[] = $location->code;
            }
        }
    }

    return $zipcodes;
}

// 🔌 AJAX: Check zip code eligibility
add_action('wp_ajax_check_zipcode_eligibility', 'check_zipcode_eligibility_ajax');
add_action('wp_ajax_nopriv_check_zipcode_eligibility', 'check_zipcode_eligibility_ajax');
function check_zipcode_eligibility_ajax() {
    $zipcode = sanitize_text_field($_POST['zipcode'] ?? '');
    wp_send_json(['eligible' => is_zipcode_eligible($zipcode)]);
}

// ✅ Validate zip code eligibility
function is_zipcode_eligible($zipcode) {
    foreach (get_eligible_zipcodes() as $eligible) {
        if (is_string($eligible) && $eligible === $zipcode) return true;

        if (is_array($eligible)) {
            if ($eligible['type'] === 'wildcard' && strpos($zipcode, $eligible['pattern']) === 0) return true;
            if ($eligible['type'] === 'range' && $zipcode >= $eligible['min'] && $zipcode <= $eligible['max']) return true;
        }
    }
    return false;
}

// 🧯 Error messages for unsupported zipcodes
add_action('woocommerce_after_checkout_billing_form', function () {
    echo '<div id="billing-zip-error" class="woocommerce-error delivery-zip-error" style="display: none;">
        We currently do not deliver to your area, please check your zipcode and try again
    </div>';
});

add_action('woocommerce_after_checkout_shipping_form', function () {
    echo '<div id="shipping-zip-error" class="woocommerce-error delivery-zip-error" style="display: none;">
        We currently do not deliver to your area, please check your zipcode and try again
    </div>';
});

// 📝 Save selected delivery slot to order
add_action('woocommerce_checkout_create_order', 'save_delivery_slot_to_order', 10, 2);
function save_delivery_slot_to_order($order, $data) {
    if (empty($_POST['delivery_slot'])) return;

    $slot_data = [
        'display' => sanitize_text_field($_POST['delivery_slot']),
        'date' => sanitize_text_field($_POST['delivery_slot_date'] ?? ''),
        'time' => sanitize_text_field($_POST['delivery_slot_time'] ?? '')
    ];

    $order->update_meta_data('_delivery_slot', wp_json_encode($slot_data));
    $order->update_meta_data('_delivery_slot_display', sanitize_text_field($_POST['delivery_slot']));
}

// 🛍️ Display selected slot at checkout
add_action('woocommerce_after_order_notes', 'display_selected_delivery_slot', 10);
function display_selected_delivery_slot($checkout) {
    $slot = $checkout->get_value('delivery_slot');
    if (!$slot) return;

    echo '<div id="selected-delivery-slot" style="margin: 15px 0; padding: 10px; background: #f8f8f8; border-radius: 4px;">';
    echo '<strong>Selected Delivery Slot:</strong> ' . esc_html($slot);
    echo '</div>';
}

// 🛒 Show slot in admin order view
add_action('woocommerce_admin_order_data_after_billing_address', 'display_delivery_slot_in_admin');
function display_delivery_slot_in_admin($order) {
    $slot = $order->get_meta('_delivery_slot_display');

    if (!$slot) {
        $data = json_decode($order->get_meta('_delivery_slot'), true);
        $slot = $data['display'] ?? '';
    }

    if ($slot) {
        echo '<p><strong>Delivery Slot:</strong> ' . esc_html($slot) . '</p>';
    }
}

// 📧 Add slot to emails
add_filter('woocommerce_email_order_meta_fields', 'add_delivery_slot_to_emails', 10, 3);
function add_delivery_slot_to_emails($fields, $sent_to_admin, $order) {
    $slot = $order->get_meta('_delivery_slot_display');

    if (!$slot) {
        $data = json_decode($order->get_meta('_delivery_slot'), true);
        $slot = $data['display'] ?? '';
    }

    if ($slot) {
        $fields['delivery_slot'] = [
            'label' => __('Delivery Slot'),
            'value' => $slot,
        ];
    }

    return $fields;
}
