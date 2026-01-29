<?php
/*
Plugin Name: Ultimate LearnDash SMS
Description: افزونه جامع پیامک لرن‌دش با قابلیت متن دوگانه (پیامک عادی و پیامک هوشمند) و پشتیبانی از ایتا و دینگ.
Version: 1.2.0
Author: MHSP :)
Author URI: https://github.com/mhsp7831
Text Domain: uls-sms
* Credits:
Original Author: صادق کاهانی 09109526082
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Kahani_Ultimate_SMS {

    // نام‌های آپشن‌ها در دیتابیس
    private $opt_general = 'uls_general_settings';
    private $opt_phones = 'uls_phone_numbers';
    private $opt_reg = 'uls_registration_settings';

    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance == null ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct() {
        // هوک‌های ادمین
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_uls_get_lessons', array( $this, 'ajax_get_lessons' ) );

        // هوک‌های منطقی (Triggers)
        add_action( 'user_register', array( $this, 'handle_registration' ) );
        add_action( 'learndash_update_course_access', array( $this, 'handle_enrollment' ), 10, 4 );
        add_action( 'learndash_lesson_completed', array( $this, 'handle_lesson_completion' ), 10, 1 );
        add_action( 'learndash_course_completed', array( $this, 'handle_course_completion' ), 10, 1 );
    }

    // ------------------------------------------------------------------------------------------------
    // بخش ادمین و UI
    // ------------------------------------------------------------------------------------------------

    public function add_admin_menu() {
        add_menu_page(
                'پیامک جامع لرن‌دش',
                'پیامک جامع لرن‌دش',
                'manage_options',
                'ultimate-learndash-sms',
                array( $this, 'render_settings_page' ),
                'dashicons-smartphone',
                50
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook != 'toplevel_page_ultimate-learndash-sms' ) {
            return;
        }

        // استایل‌های ادمین (CSS Inline)
        add_action( 'admin_head', function () {
            ?>
            <style>
                .uls-wrap {
                    font-family: Vazirmatn;
                    direction: rtl;
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    margin: 20px 0;
                    max-width: 1200px;
                }

                .uls-header {
                    border-bottom: 2px solid #eee;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                }

                .uls-nav-tab-wrapper {
                    border-bottom: 1px solid #ccc;
                    margin-bottom: 20px;
                    padding-bottom: 0;
                }

                .uls-nav-tab {
                    display: inline-block;
                    padding: 10px 20px;
                    text-decoration: none;
                    border: 1px solid #ccc;
                    border-bottom: none;
                    background: #f7f7f7;
                    color: #555;
                    margin-left: 5px;
                    border-radius: 5px 5px 0 0;
                }

                .uls-nav-tab.nav-tab-active {
                    background: #fff;
                    border-bottom: 1px solid #fff;
                    font-weight: bold;
                    color: #000;
                    margin-bottom: -1px;
                }

                .uls-form-row {
                    margin-bottom: 15px;
                }

                .uls-form-row label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 5px;
                }

                .uls-form-row input[type="text"], .uls-form-row input[type="password"], .uls-form-row textarea, .uls-form-row select {
                    width: 100%;
                    max-width: 500px;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }

                .uls-card {
                    background: #f9f9f9;
                    border: 1px solid #e5e5e5;
                    padding: 15px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                    border-right: 4px solid #0073aa;
                }

                .uls-card h3 {
                    margin-top: 0;
                }

                .uls-btn {
                    cursor: pointer;
                }

                .uls-del-btn {
                    background: #dc3232;
                    color: #fff;
                    border: none;
                    padding: 5px 10px;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 12px;
                }

                .uls-log-box {
                    background: #222;
                    color: #0f0;
                    padding: 10px;
                    height: 200px;
                    overflow-y: scroll;
                    direction: ltr;
                    text-align: left;
                    font-family: monospace;
                }

                /* استایل شمارنده کاراکتر */
                .uls-char-count {
                    font-size: 12px;
                    color: #777;
                    margin-top: 5px;
                    text-align: left;
                    width: 100%;
                    max-width: 500px;
                    font-family: Tahoma, sans-serif;
                }

                /* استایل‌های مربوط به Smart Message */
                .uls-smart-row {
                    display: none;
                    margin-top: 15px;
                    padding: 10px;
                    background: #e0f7fa;
                    border-right: 3px solid #00bcd4;
                    border-radius: 4px;
                    max-width: 520px;
                }

                .uls-smart-label {
                    color: #006064;
                }

                .uls-checkbox-label {
                    display: inline-flex;
                    align-items: center;
                    cursor: pointer;
                    user-select: none;
                }

                .uls-checkbox-label input {
                    margin-left: 8px;
                }
            </style>
            <?php
        } );

        // اسکریپت‌های ادمین (JS Inline)
        add_action( 'admin_footer', function () {
            ?>
            <script>
                jQuery(document).ready(function ($) {

                    // --- AJAX for Lessons Dropdown ---
                    $('#uls_selected_course').change(function () {
                        var course_id = $(this).val();
                        $('#uls_selected_lesson').empty().append('<option value="">در حال بارگذاری...</option>').prop('disabled', true);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {action: 'uls_get_lessons', course_id: course_id},
                            success: function (response) {
                                $('#uls_selected_lesson').empty().append('<option value="">یک درس انتخاب کنید</option>').prop('disabled', false);
                                if (response.success && response.data.length > 0) {
                                    $.each(response.data, function (i, lesson) {
                                        $('#uls_selected_lesson').append('<option value="' + lesson.id + '">' + lesson.title + '</option>');
                                    });
                                } else {
                                    $('#uls_selected_lesson').append('<option value="">هیچ درسی یافت نشد</option>');
                                }
                            }
                        });
                    });

                    // --- Real-time Character Counter ---
                    function updateCharCount(textarea) {
                        var $el = $(textarea);
                        var count = $el.val().length;
                        var $counter = $el.next('.uls-char-count');
                        if ($counter.length === 0) {
                            $el.after('<div class="uls-char-count"></div>');
                            $counter = $el.next('.uls-char-count');
                        }
                        $counter.text('تعداد کاراکتر: ' + count + ' | ' + Math.ceil(count / 67) + ' پیامک');
                    }

                    $('.uls-wrap textarea').each(function () {
                        updateCharCount(this);
                    });
                    $(document).on('input focus', '.uls-wrap textarea', function () {
                        updateCharCount(this);
                    });

                    // --- Smart Message Toggle Logic ---
                    $(document).on('change', '.uls-smart-toggle', function () {
                        var isChecked = $(this).is(':checked');
                        // پیدا کردن نزدیک‌ترین کانتینر (card برای دوره‌ها/درس‌ها یا wrap برای ثبت‌نام)
                        var $container = $(this).closest('.uls-card, .uls-section-wrap');
                        var $smartRow = $container.find('.uls-smart-row');

                        if (isChecked) {
                            $smartRow.slideDown();
                        } else {
                            $smartRow.slideUp();
                        }
                    });

                    // بررسی اولیه چک‌باکس‌ها هنگام لود صفحه
                    $('.uls-smart-toggle').each(function () {
                        $(this).trigger('change');
                    });
                });
            </script>
            <?php
        } );
    }

    public function ajax_get_lessons() {
        $course_id = intval( $_POST['course_id'] );
        $lessons   = get_posts( array(
                'post_type'   => 'sfwd-lessons',
                'meta_key'    => 'course_id',
                'meta_value'  => $course_id,
                'numberposts' => - 1
        ) );
        $data      = array();
        if ( $lessons ) {
            foreach ( $lessons as $l ) {
                $data[] = array( 'id' => $l->ID, 'title' => $l->post_title );
            }
        }
        wp_send_json_success( $data );
    }

    public function render_settings_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
        ?>
        <div class="wrap uls-wrap">
            <div class="uls-header"><h1>تنظیمات جامع پیامک لرن‌دش</h1></div>
            <div class="uls-nav-tab-wrapper">
                <a href="?page=ultimate-learndash-sms&tab=general"
                   class="uls-nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">تنظیمات کلی</a>
                <a href="?page=ultimate-learndash-sms&tab=phones"
                   class="uls-nav-tab <?php echo $active_tab == 'phones' ? 'nav-tab-active' : ''; ?>">شماره‌ها</a>
                <a href="?page=ultimate-learndash-sms&tab=registration"
                   class="uls-nav-tab <?php echo $active_tab == 'registration' ? 'nav-tab-active' : ''; ?>">عضویت در
                    سایت</a>
                <a href="?page=ultimate-learndash-sms&tab=enrollment"
                   class="uls-nav-tab <?php echo $active_tab == 'enrollment' ? 'nav-tab-active' : ''; ?>">ثبت نام
                    دوره</a>
                <a href="?page=ultimate-learndash-sms&tab=lesson"
                   class="uls-nav-tab <?php echo $active_tab == 'lesson' ? 'nav-tab-active' : ''; ?>">تکمیل درس</a>
                <a href="?page=ultimate-learndash-sms&tab=completion"
                   class="uls-nav-tab <?php echo $active_tab == 'completion' ? 'nav-tab-active' : ''; ?>">تکمیل دوره</a>
            </div>
            <form method="post" action="">
                <?php
                wp_nonce_field( 'uls_save_settings', 'uls_nonce' );
                switch ( $active_tab ) {
                    case 'general':
                        $this->render_tab_general();
                        break;
                    case 'phones':
                        $this->render_tab_phones();
                        break;
                    case 'registration':
                        $this->render_tab_registration();
                        break;
                    case 'enrollment':
                        $this->render_tab_enrollment();
                        break;
                    case 'lesson':
                        $this->render_tab_lesson();
                        break;
                    case 'completion':
                        $this->render_tab_completion();
                        break;
                    default:
                        $this->render_tab_general();
                        break;
                }
                ?>
            </form>
        </div>
        <?php
    }

    // --- Tab Renderers ---

    private function render_tab_general() {
        if ( isset( $_POST['save_general'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            $data = array(
                    'username'     => sanitize_text_field( $_POST['username'] ),
                    'password'     => sanitize_text_field( $_POST['password'] ),
                    'token'        => sanitize_text_field( $_POST['token'] ),
                    'ding'         => sanitize_text_field( $_POST['ding'] ),
                    'default_from' => sanitize_text_field( $_POST['default_from'] ),
            );
            update_option( $this->opt_general, $data );
            if ( isset( $_POST['clear_log'] ) ) {
                file_put_contents( plugin_dir_path( __FILE__ ) . 'debug_log.txt', '' );
            }
            echo '<div class="updated"><p>تنظیمات کلی ذخیره شد.</p></div>';
        }
        $settings = get_option( $this->opt_general, array() );
        ?>
        <h3>اطلاعات پنل پیامک و ربات (Global)</h3>
        <div class="uls-form-row"><label>نام کاربری پنل پیامک:</label><input type="text" name="username"
                                                                             value="<?php echo esc_attr( $settings['username'] ?? '' ); ?>">
        </div>
        <div class="uls-form-row"><label>رمز عبور پنل پیامک:</label><input type="password" name="password"
                                                                           value="<?php echo esc_attr( $settings['password'] ?? '' ); ?>">
        </div>
        <div class="uls-form-row"><label>شماره فرستنده پیش‌فرض:</label><input type="text" name="default_from"
                                                                              value="<?php echo esc_attr( $settings['default_from'] ?? '' ); ?>">
        </div>
        <hr>
        <div class="uls-form-row"><label>توکن ربات ایتا:</label><input type="text" name="token"
                                                                       value="<?php echo esc_attr( $settings['token'] ?? '' ); ?>">
        </div>
        <div class="uls-form-row"><label>شماره خط دینگ:</label><input type="text" name="ding"
                                                                      value="<?php echo esc_attr( $settings['ding'] ?? '' ); ?>">
        </div>
        <input type="submit" name="save_general" class="button button-primary" value="ذخیره تنظیمات">
        <hr>
        <h3>فایل لاگ (Debug Log)</h3>
        <div class="uls-log-box"><?php echo esc_html( @file_get_contents( plugin_dir_path( __FILE__ ) . 'debug_log.txt' ) ); ?></div>
        <div style="margin-top:10px;"><label><input type="checkbox" name="clear_log" value="1"> پاکسازی فایل لاگ هنگام
                ذخیره</label></div>
        <?php
    }

    private function render_tab_phones() {
        $phones = get_option( $this->opt_phones, array() );
        if ( isset( $_POST['add_phone'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            if ( ! empty( $_POST['new_phone'] ) ) {
                $phones[] = array(
                        'number' => sanitize_text_field( $_POST['new_phone'] ),
                        'label'  => sanitize_text_field( $_POST['new_label'] )
                );
                update_option( $this->opt_phones, $phones );
                echo '<div class="updated"><p>شماره اضافه شد.</p></div>';
            }
        }
        if ( isset( $_POST['delete_phone_idx'] ) ) {
            $idx = intval( $_POST['delete_phone_idx'] );
            if ( isset( $phones[ $idx ] ) ) {
                unset( $phones[ $idx ] );
                update_option( $this->opt_phones, array_values( $phones ) );
                echo '<div class="updated"><p>شماره حذف شد.</p></div>';
                $phones = array_values( $phones );
            }
        }
        ?>
        <h3>مدیریت شماره‌های فرستنده</h3>
        <div style="background:#f1f1f1; padding:15px; border-radius:5px; margin-bottom:20px;">
            <h4>افزودن شماره جدید</h4>
            <input type="text" name="new_phone" placeholder="شماره" style="width:200px;">
            <input type="text" name="new_label" placeholder="برچسب" style="width:200px;">
            <input type="submit" name="add_phone" class="button" value="افزودن">
        </div>
        <?php if ( ! empty( $phones ) ): ?>
            <table class="widefat fixed striped">
                <thead>
                <tr>
                    <th>شماره</th>
                    <th>برچسب</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody><?php foreach ( $phones as $idx => $ph ): ?>
                    <tr>
                    <td><?php echo esc_html( $ph['number'] ); ?></td>
                    <td><?php echo esc_html( $ph['label'] ); ?></td>
                    <td>
                        <button type="submit" name="delete_phone_idx" value="<?php echo $idx; ?>" class="uls-del-btn">
                            حذف
                        </button>
                    </td></tr><?php endforeach; ?></tbody>
            </table>
        <?php else: echo '<p>شماره‌ای ثبت نشده است.</p>'; endif;
    }

    private function render_tab_registration() {
        if ( isset( $_POST['save_reg'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            $data = array(
                    'active'        => isset( $_POST['reg_active'] ) ? 1 : 0,
                    'message'       => sanitize_textarea_field( $_POST['reg_message'] ),
                    'smart_send'    => isset( $_POST['reg_smart'] ) ? 1 : 0,
                    'smart_message' => sanitize_textarea_field( $_POST['reg_smart_message'] ),
                    'from'          => sanitize_text_field( $_POST['reg_from'] )
            );
            update_option( $this->opt_reg, $data );
            echo '<div class="updated"><p>تنظیمات ثبت‌نام ذخیره شد.</p></div>';
        }
        $reg     = get_option( $this->opt_reg, array() );
        $phones  = get_option( $this->opt_phones, array() );
        $general = get_option( $this->opt_general, array() );
        ?>
        <h3>تنظیمات پیامک عضویت کاربر جدید</h3>
        <p>پیام عضویت <span style="color: red">نمی تواند</span> از طریق برنامک ارسال شود.</p>
        <div class="uls-section-wrap">
            <div class="uls-form-row"><label><input type="checkbox" name="reg_active"
                                                    value="1" <?php checked( 1, $reg['active'] ?? 0 ); ?>> فعال‌سازی
                    ارسال پیامک هنگام عضویت</label></div>

            <div class="uls-form-row">
                <label>متن پیامک عادی (SMS Message):</label>
                <textarea name="reg_message" rows="5"><?php echo esc_textarea( $reg['message'] ?? '' ); ?></textarea>
                <p class="description">نام کاربری به صورت خودکار به انتهای پیام اضافه میشود. <span style="color: red">(25 کاراکتر)</span>
                </p>
            </div>

            <div class="uls-form-row">
                <label>شماره فرستنده:</label>
                <select name="reg_from">
                    <option value="">پیش‌فرض (<?php echo esc_html( $general['default_from'] ?? 'تعیین نشده' ); ?>)
                    </option><?php foreach ( $phones as $p ) {
                        echo "<option value='{$p['number']}' " . selected( $reg['from'] ?? '', $p['number'], false ) . ">{$p['label']}</option>";
                    } ?></select>
            </div>

            <div class="uls-form-row">
                <label class="uls-checkbox-label">
                    <input type="checkbox" name="reg_smart" class="uls-smart-toggle"
                           value="1" <?php checked( 1, $reg['smart_send'] ?? 0 ); ?>>
                    ارسال هوشمند (برنامک -> دینگ -> پیامک)
                </label>
            </div>

            <div class="uls-smart-row">
                <div class="uls-form-row">
                    <label class="uls-smart-label">متن هوشمند (این متن فقط در ایتا/دینگ استفاده میشود):</label>
                    <textarea name="reg_smart_message" rows="5"
                              placeholder="متن مخصوص ایتا/دینگ..."><?php echo esc_textarea( $reg['smart_message'] ?? '' ); ?></textarea>
                    <p class="description">نام کاربری به صورت خودکار به انتهای پیام اضافه میشود. (25 کاراکتر)</p>
                </div>
            </div>
        </div>
        <input type="submit" name="save_reg" class="button button-primary" value="ذخیره تنظیمات">
        <?php
    }

    private function render_tab_enrollment() {
        $this->handle_course_logic( 'enroll' );
    }

    private function render_tab_completion() {
        $this->handle_course_logic( 'complete' );
    }

    private function handle_course_logic( $type ) {
        $option_prefix = ( $type == 'enroll' ) ? 'uls_course_enroll_' : 'uls_course_complete_';
        $page_title    = ( $type == 'enroll' ) ? 'تنظیمات پیامک ثبت نام دوره (Enrollment)' : 'تنظیمات پیامک تکمیل دوره (Completion)';

        if ( isset( $_POST['save_courses'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            if ( isset( $_POST['courses'] ) ) {
                foreach ( $_POST['courses'] as $cid => $cdata ) {
                    $data = array(
                            'active'        => isset( $cdata['active'] ) ? 1 : 0,
                            'message'       => sanitize_textarea_field( $cdata['message'] ),
                            'smart'         => isset( $cdata['smart'] ) ? 1 : 0,
                            'smart_message' => sanitize_textarea_field( $cdata['smart_message'] ),
                            'from'          => sanitize_text_field( $cdata['from'] )
                    );
                    update_option( $option_prefix . $cid, $data );
                }
                echo '<div class="updated"><p>تنظیمات دوره‌ها ذخیره شد.</p></div>';
            }
        }

        $courses = get_posts( array( 'post_type' => 'sfwd-courses', 'numberposts' => - 1 ) );
        $phones  = get_option( $this->opt_phones, array() );

        echo "<h3>$page_title</h3><p>برای هر دوره می‌توانید پیامک اختصاصی تنظیم کنید.</p>";

        foreach ( $courses as $course ) {
            $opt       = get_option( $option_prefix . $course->ID, array() );
            $is_active = isset( $opt['active'] ) && $opt['active'] == 1;
            $bg        = $is_active ? '#e7f7ff' : '#f9f9f9';
            $border    = $is_active ? '#0073aa' : '#e5e5e5';

            echo "<div class='uls-card' style='background:$bg; border-right-color:$border;'>";
            echo "<h4>" . esc_html( $course->post_title ) . "</h4>";
            echo "<div class='uls-form-row'><label><input type='checkbox' name='courses[$course->ID][active]' value='1' " . checked( 1, $opt['active'] ?? 0, false ) . "> فعال‌سازی</label></div>";

            echo "<div class='uls-form-row'><label>متن پیامک عادی:</label><textarea name='courses[$course->ID][message]' rows='3'>" . esc_textarea( $opt['message'] ?? '' ) . "</textarea></div>";

            echo "<div class='uls-form-row'><label>شماره:</label><select name='courses[$course->ID][from]'><option value=''>پیش‌فرض</option>";
            foreach ( $phones as $p ) {
                echo "<option value='{$p['number']}' " . selected( $opt['from'] ?? '', $p['number'], false ) . ">{$p['label']}</option>";
            }
            echo "</select></div>";

            echo "<div class='uls-form-row'><label class='uls-checkbox-label'><input type='checkbox' name='courses[$course->ID][smart]' class='uls-smart-toggle' value='1' " . checked( 1, $opt['smart'] ?? 0, false ) . "> ارسال هوشمند (برنامک -> دینگ -> پیامک)</label></div>";

            // متن هوشمند (مخفی)
            echo "<div class='uls-smart-row'><div class='uls-form-row'><label class='uls-smart-label'>متن هوشمند (این متن فقط در ایتا/دینگ استفاده میشود):</label><textarea name='courses[$course->ID][smart_message]' rows='3'>" . esc_textarea( $opt['smart_message'] ?? '' ) . "</textarea></div></div>";

            echo "</div>";
        }
        echo '<input type="submit" name="save_courses" class="button button-primary" value="ذخیره همه دوره‌ها">';
    }

    private function render_tab_lesson() {
        // 1. Add New (Save Logic)
        if ( isset( $_POST['add_lesson_msg'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            $cid = intval( $_POST['selected_course'] );
            $lid = intval( $_POST['selected_lesson'] );
            if ( $cid && $lid ) {
                $data = array(
                        'message'       => sanitize_textarea_field( $_POST['new_lesson_message'] ),
                        'from'          => sanitize_text_field( $_POST['new_lesson_from'] ),
                        'smart'         => isset( $_POST['new_lesson_smart'] ) ? 1 : 0,
                        'smart_message' => sanitize_textarea_field( $_POST['new_lesson_smart_message'] )
                );
                update_option( "uls_lesson_{$cid}_{$lid}", $data );
                echo '<div class="updated"><p>پیام درس افزوده شد.</p></div>';
            }
        }

        // 2. Save List (Edit Logic)
        if ( isset( $_POST['save_lesson_list'] ) && check_admin_referer( 'uls_save_settings', 'uls_nonce' ) ) {
            if ( isset( $_POST['lessons'] ) ) {
                foreach ( $_POST['lessons'] as $key => $ldata ) {
                    if ( isset( $ldata['delete'] ) && $ldata['delete'] == 1 ) {
                        delete_option( "uls_lesson_{$key}" );
                    } else {
                        $data = array(
                                'message'       => sanitize_textarea_field( $ldata['message'] ),
                                'from'          => sanitize_text_field( $ldata['from'] ),
                                'smart'         => isset( $ldata['smart'] ) ? 1 : 0,
                                'smart_message' => sanitize_textarea_field( $ldata['smart_message'] )
                        );
                        update_option( "uls_lesson_{$key}", $data );
                    }
                }
                echo '<div class="updated"><p>تغییرات درس‌ها ذخیره شد.</p></div>';
            }
        }

        $courses = get_posts( array( 'post_type' => 'sfwd-courses', 'numberposts' => - 1 ) );
        $phones  = get_option( $this->opt_phones, array() );
        $general = get_option( $this->opt_general, array() ); // دریافت تنظیمات کلی برای نمایش شماره پیش‌فرض

        ?>
        <h3>تنظیمات پیامک تکمیل درس</h3>

        <div class="uls-card" style="border-right-color: #46b450;">
            <h3>افزودن پیام برای درس جدید</h3>

            <div class="uls-form-row">
                <label>انتخاب دوره:</label>
                <select id="uls_selected_course" name="selected_course">
                    <option value="">انتخاب کنید...</option>
                    <?php foreach ( $courses as $c ) {
                        echo "<option value='{$c->ID}'>{$c->post_title}</option>";
                    } ?>
                </select>
            </div>

            <div class="uls-form-row">
                <label>انتخاب درس:</label>
                <select id="uls_selected_lesson" name="selected_lesson" disabled>
                    <option>ابتدا دوره را انتخاب کنید</option>
                </select>
            </div>

            <div class="uls-form-row">
                <label>متن پیامک عادی:</label>
                <textarea name="new_lesson_message" rows="3"></textarea>
            </div>

            <div class="uls-form-row">
                <label>شماره فرستنده:</label>
                <?php if ( ! empty( $phones ) ): ?>
                    <select name="new_lesson_from">
                        <option value="">پیش‌فرض سیستم
                            (<?php echo esc_html( $general['default_from'] ?? 'تعیین نشده' ); ?>)
                        </option>
                        <?php foreach ( $phones as $p ): ?>
                            <option value="<?php echo esc_attr( $p['number'] ); ?>">
                                <?php echo esc_html( $p['label'] . ' (' . $p['number'] . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="new_lesson_from" placeholder="شماره فرستنده (مثلا 3000...)">
                <?php endif; ?>
            </div>

            <div class="uls-form-row">
                <label class="uls-checkbox-label">
                    <input type="checkbox" name="new_lesson_smart" class="uls-smart-toggle" value="1">
                    ارسال هوشمند (برنامک -> دینگ -> پیامک)
                </label>
            </div>

            <div class="uls-smart-row">
                <div class="uls-form-row">
                    <label class="uls-smart-label">متن هوشمند:</label>
                    <textarea name="new_lesson_smart_message" rows="3"></textarea>
                </div>
            </div>

            <input type="submit" name="add_lesson_msg" class="button button-primary" value="افزودن پیام درس">
        </div>

        <hr>

        <h3>درس‌های دارای پیامک فعال</h3>
        <?php
        $has_lesson = false;
        foreach ( $courses as $course ) {
            $lessons = get_posts( array(
                    'post_type'   => 'sfwd-lessons',
                    'meta_key'    => 'course_id',
                    'meta_value'  => $course->ID,
                    'numberposts' => - 1
            ) );
            if ( ! $lessons ) {
                continue;
            }

            foreach ( $lessons as $lesson ) {
                $key = "uls_lesson_{$course->ID}_{$lesson->ID}";
                $opt = get_option( $key );

                if ( $opt !== false ) {
                    $has_lesson = true;
                    echo "<div class='uls-card'>";
                    echo "<h4>{$course->post_title} > {$lesson->post_title}</h4>";

                    // Textarea Regular
                    echo "<div class='uls-form-row'><label>عادی:</label><textarea name='lessons[{$course->ID}_{$lesson->ID}][message]'>" . esc_textarea( $opt['message'] ) . "</textarea></div>";

                    // Select From (Existing Logic for List Items)
                    echo "<div class='uls-form-row'><select name='lessons[{$course->ID}_{$lesson->ID}][from]'><option value=''>پیش‌فرض</option>";
                    foreach ( $phones as $p ) {
                        echo "<option value='{$p['number']}' " . selected( $opt['from'], $p['number'], false ) . ">{$p['label']}</option>";
                    }
                    echo "</select></div>";

                    // Smart Toggle & Message
                    echo "<div class='uls-form-row'><label class='uls-checkbox-label'><input type='checkbox' name='lessons[{$course->ID}_{$lesson->ID}][smart]' class='uls-smart-toggle' value='1' " . checked( 1, $opt['smart'] ?? 0, false ) . "> ارسال هوشمند (برنامک -> دینگ -> پیامک)</label></div>";
                    echo "<div class='uls-smart-row'><div class='uls-form-row'><label class='uls-smart-label'>متن هوشمند (این متن فقط در ایتا/دینگ استفاده میشود):</label><textarea name='lessons[{$course->ID}_{$lesson->ID}][smart_message]'>" . esc_textarea( $opt['smart_message'] ?? '' ) . "</textarea></div></div>";

                    echo "<div style='margin-top:10px;'><label style='color:red;'><input type='checkbox' name='lessons[{$course->ID}_{$lesson->ID}][delete]' value='1'> حذف این پیام</label></div>";
                    echo "</div>";
                }
            }
        }
        if ( $has_lesson ) {
            echo '<input type="submit" name="save_lesson_list" class="button button-primary" value="ذخیره تغییرات لیست">';
        } else {
            echo '<p>هنوز پیامی برای درسی ثبت نشده است.</p>';
        }
    }


    // ------------------------------------------------------------------------------------------------
    // منطق اصلی ارسال (Core Logic)
    // ------------------------------------------------------------------------------------------------

    /**
     * متد مرکزی ارسال پیام
     *
     * @param string $mobile
     * @param string $regular_message متن پیامک عادی (SMS)
     * @param string $smart_message متن پیامک هوشمند (ایتا/دینگ)
     * @param bool $force_smart_send آیا ارسال هوشمند فعال است؟
     * @param string $custom_from
     */
    private function send_notification( $mobile, $regular_message, $smart_message, $force_smart_send = false, $custom_from = '' ) {
        global $wpdb;

        $general      = get_option( $this->opt_general, array() );
        $username     = $general['username'] ?? '';
        $password     = $general['password'] ?? '';
        $token        = $general['token'] ?? '';
        $ding_number  = $general['ding'] ?? '';
        $default_from = $general['default_from'] ?? '';
        $final_from   = ! empty( $custom_from ) ? $custom_from : $default_from;

        $this->log( "Start sending to: $mobile | Smart: " . ( $force_smart_send ? 'Yes' : 'No' ) );

        $sent_via_eitaa = false;
        $sent_via_ding  = false;

        // --- گام 1: بررسی ارسال هوشمند (ایتا و دینگ) ---
        if ( $force_smart_send ) {
            // اگر متن هوشمند خالی بود، برای جلوگیری از ارسال خالی، از متن عادی استفاده کن (اختیاری)
            // اما طبق درخواست، فرض می‌کنیم کاربر متن هوشمند را پر کرده است.
            $msg_to_send_smart = ! empty( $smart_message ) ? $smart_message : $regular_message;

            // الف) ایتا
            $table_name = $wpdb->prefix . 'eitaa_users';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
                $eitaa_id = $wpdb->get_var( $wpdb->prepare( "SELECT eitaa_id FROM $table_name WHERE phone = %s", $mobile ) );
                if ( $eitaa_id && ! empty( $token ) ) {
                    $this->log( "Sending via Eitaa..." );
                    $response = $this->send_via_eitaa_api( $token, $eitaa_id, $msg_to_send_smart );
                    $decoded  = json_decode( $response, true );
                    if ( is_array( $decoded ) && isset( $decoded['ok'] ) && $decoded['ok'] === true && isset( $decoded['result'] ) && $decoded['result'] === 'success' ) {
                        $sent_via_eitaa = true;
                        $this->log( "Eitaa Success." );
                    } else {
                        $this->log( "Eitaa Failed: " . print_r( $decoded, true ) );
                    }
                }
            }

            // ب) دینگ (اگر ایتا ناموفق بود)
            if ( ! $sent_via_eitaa && ! empty( $ding_number ) ) {
                $this->log( "Sending via Ding..." );
                $url_ding = "http://tsms.ir/url/tsmshttp.php?from=$ding_number&to=$mobile&username=$username&password=$password&message=" . urlencode( $msg_to_send_smart );
                $response = wp_remote_get( $url_ding );
                if ( ! is_wp_error( $response ) ) {
                    $body        = trim( wp_remote_retrieve_body( $response ) );
                    $error_codes = array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '14' );
                    if ( ! in_array( $body, $error_codes ) ) {
                        $sent_via_ding = true;
                        $this->log( "Ding Success. Ref: $body" );
                    } else {
                        $this->log( "Ding Error Code: $body" );
                    }
                }
            }
        }

        // --- گام 2: فال‌بک به SMS (اگر هوشمند موفق نبود یا غیرفعال بود) ---
        // نکته: اگر هوشمند فعال بود ولی ناموفق، باید پیامک "عادی" ($regular_message) ارسال شود.
        if ( ! $sent_via_eitaa && ! $sent_via_ding ) {
            if ( ! empty( $final_from ) ) {
                $this->log( "Fallback to Standard SMS ($final_from)..." );
                $url_sms  = "http://tsms.ir/url/tsmshttp.php?from=$final_from&to=$mobile&username=$username&password=$password&message=" . urlencode( $regular_message );
                $response = wp_remote_get( $url_sms );
                if ( ! is_wp_error( $response ) ) {
                    $this->log( "SMS Request Sent. Result: " . wp_remote_retrieve_body( $response ) );
                } else {
                    $this->log( "SMS Connection Error." );
                }
            } else {
                $this->log( "Aborted: No Sender Number available." );
            }
        }
    }

    private function send_via_eitaa_api( $token, $chat_id, $message ) {
        $url  = 'https://eitaayar.ir/api/app/sendMessage';
        $data = array( 'token' => $token, 'chat_id' => $chat_id, 'text' => $message );
        $ch   = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
        $response = curl_exec( $ch );
        $error    = curl_error( $ch );
        curl_close( $ch );
        if ( $error ) {
            return json_encode( array( 'ok' => false, 'error' => $error ) );
        }

        return $response;
    }

    // ------------------------------------------------------------------------------------------------
    // تریگرها (Event Handlers) - آپدیت شده برای ارسال دو متن
    // ------------------------------------------------------------------------------------------------

    public function handle_registration( $user_id ) {
        $reg_opt = get_option( $this->opt_reg, array() );
        if ( ! isset( $reg_opt['active'] ) || $reg_opt['active'] != 1 ) {
            return;
        }

        $user_info = get_userdata( $user_id );
        $mobile    = trim( $user_info->user_login );

        // ساخت متن عادی
        $reg_msg       = $reg_opt['message'] ?? '';
        $final_reg_msg = $reg_msg . "\n\nنام کاربری: " . $mobile;

        // ساخت متن هوشمند
        $smart_raw = $reg_opt['smart_message'] ?? '';
        // اگر متن هوشمند هم نیاز به یوزر/پسورد دارد، می‌توان اینجا اضافه کرد. فعلا همان متن خام ارسال می‌شود.
        // اگر کاربر بخواهد یوزر/پسورد در متن هوشمند باشد باید خودش بنویسد یا ما اضافه کنیم؟
        // معمولا برای ایتا امنیت بالاتر است، پس یوزر/پسورد را به متن هوشمند هم اضافه می‌کنیم.
        $final_smart_msg = ! empty( $smart_raw ) ? ( $smart_raw . "\n\nنام کاربری: " . $mobile ) : '';

        $this->log( "Event: Registration - User: $mobile" );

        $this->send_notification(
                $mobile,
                $final_reg_msg,
                $final_smart_msg,
                ( isset( $reg_opt['smart_send'] ) && $reg_opt['smart_send'] == 1 ),
                $reg_opt['from'] ?? ''
        );
    }

    public function handle_enrollment( $user_id, $course_id, $access_list, $remove ) {
        if ( $remove ) {
            return;
        }
        $opt = get_option( 'uls_course_enroll_' . $course_id, array() );
        if ( ! isset( $opt['active'] ) || $opt['active'] != 1 ) {
            return;
        }
        if ( empty( $opt['message'] ) ) {
            return;
        }

        $user_info = get_userdata( $user_id );
        $mobile    = trim( $user_info->user_login );
        $this->log( "Event: Enrollment - User: $mobile, Course: $course_id" );

        $this->send_notification(
                $mobile,
                $opt['message'], // SMS
                $opt['smart_message'] ?? '', // Smart
                ( isset( $opt['smart'] ) && $opt['smart'] == 1 ),
                $opt['from'] ?? ''
        );
    }

    public function handle_course_completion( $data ) {
        $user_id   = $data['user']->ID;
        $course_id = $data['course']->ID;
        $opt       = get_option( 'uls_course_complete_' . $course_id, array() );
        if ( ! isset( $opt['active'] ) || $opt['active'] != 1 ) {
            return;
        }
        if ( empty( $opt['message'] ) ) {
            return;
        }

        $user_info = get_userdata( $user_id );
        $mobile    = trim( $user_info->user_login );
        $this->log( "Event: Course Complete - User: $mobile, Course: $course_id" );

        $this->send_notification(
                $mobile,
                $opt['message'],
                $opt['smart_message'] ?? '',
                ( isset( $opt['smart'] ) && $opt['smart'] == 1 ),
                $opt['from'] ?? ''
        );
    }

    public function handle_lesson_completion( $data ) {
        static $processed_lessons = array();
        $user_id   = $data['user']->ID;
        $course_id = $data['course']->ID;
        $lesson_id = $data['lesson']->ID;

        $unique_key = "{$user_id}_{$course_id}_{$lesson_id}";
        if ( isset( $processed_lessons[ $unique_key ] ) ) {
            return;
        }
        $processed_lessons[ $unique_key ] = true;

        $key = "uls_lesson_{$course_id}_{$lesson_id}";
        $opt = get_option( $key );
        if ( ! $opt || empty( $opt['message'] ) ) {
            return;
        }

        $user_info = get_userdata( $user_id );
        $mobile    = trim( $user_info->user_login );
        $this->log( "Event: Lesson Complete - User: $mobile, Lesson: $lesson_id" );

        $this->send_notification(
                $mobile,
                $opt['message'],
                $opt['smart_message'] ?? '',
                ( isset( $opt['smart'] ) && $opt['smart'] == 1 ),
                $opt['from'] ?? ''
        );
    }

    private function log( $message ) {
        $log_file = plugin_dir_path( __FILE__ ) . 'debug_log.txt';
        $this->secure_log_file();
        $time = date_i18n( 'Y-m-d H:i:s' );
        @file_put_contents( $log_file, "[$time] $message" . PHP_EOL, FILE_APPEND );
    }

    private function secure_log_file() {
        $htaccess_file = plugin_dir_path( __FILE__ ) . '.htaccess';
        if ( ! file_exists( $htaccess_file ) ) {
            @file_put_contents( $htaccess_file, "<Files debug_log.txt>\nOrder Allow,Deny\nDeny from all\n</Files>" );
        }
    }
}

Kahani_Ultimate_SMS::get_instance();