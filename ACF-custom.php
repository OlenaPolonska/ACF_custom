<?php
/*
* Custom ACF field for the selector of Contacts for CRM Groundhogg. 
* Styles and scripts are embedded in the PHP file as required by the project. 
* If this code were implemented as a plugin, the styles and scripts would be moved to a separate file.
*/

if( ! class_exists( 'Groundhogg_Condition_Logic' ) ) :

    class Groundhogg_Condition_Logic extends ACF_Field {

        private $meta_fields_cache = null;

        function __construct() {
            add_action( 'admin_print_footer_scripts', array( $this, 'render_js' ) );
            add_action( 'admin_print_styles', array( $this, 'render_css' ) );
            add_action( 'admin_head', array( $this, 'add_datalist' ) );

            parent::__construct();
        }

        /*
        * Defines all field options
        *
        */
        function initialize() {
            $this->name = 'gh_condition_logic';
                $this->label = esc_html__( 'Groundhogg Condtion Logic', 'groundhogg' );
                $this->category = 'Choice';
                $this->defaults = array(
                    'default_value' => array(
                        '1' => array(),
                    ),
                );
        }

        /*
        * Returns all accessible fields: custom meta fields, plus owner, age, tags, and contact data
        *
        * @return  array
        */
        private function get_meta_fields(): array {
            if ( $this->meta_fields_cache !== null ) {
                return $this->meta_fields_cache;
            }

            $mappable_fields = Groundhogg\get_mappable_fields();

            $fields = array_merge( $mappable_fields['Contact Info'] ?? [], $mappable_fields['Address'] ?? [] );
            if ( isset( $fields['contact_id'] ) ) {
                unset( $fields['contact_id'] );
            }

            $contact_fields = Groundhogg\Properties::instance()->get_fields();
            $this->meta_fields_cache = array_unique( array_merge( array_keys( $fields ), array_column( $contact_fields, 'name' ) ?? [], [ 'owner', 'tags', 'age', ] ) );

            return $this->meta_fields_cache;
        }

        /*
        * Returns comparing options for the meta fields
        *
        * @return  array
        */
        private function comparing_options(): array {
            return [
                'equal to' => [
                    'title' => esc_html__( 'Equal', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'not equal to' => [
                    'title' => esc_html__( 'Not equal', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'contains' => [
                    'title' => esc_html__( 'Contains', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'does not contain' => [
                    'title' => esc_html__( 'Does not contain', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'is empty' => [
                    'title' => esc_html__( 'Is empty', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'is not empty' => [
                    'title' => esc_html__( 'Is not empty', 'groundhogg' ),
                    'class' => 'meta-field-options',
                ],
                'has' => [
                    'title' => esc_html__( 'Include', 'groundhogg' ),
                    'class' => 'tag-options',
                ],
                'does not have' => [
                    'title' => esc_html__( 'Does not include', 'groundhogg' ),
                    'class' => 'tag-options',
                ],
            ];
        }

        /*
        * Returns HTML code for single OR condition. The indexes and values for specific fields should be replaced with sprintf.
        * Is used for initial building of the fields, and for adding more OR conditions
        *
        * @return  string
        */
        private function or_condition_html(): string {
            return '<fieldset class="or-condition search-filters-editor" data-or-number="%1$s">
                <legend> OR </legend>

                <div class="group ui-sortable">
                %2$s

                <div class="add-remove-filter-container">
                <button class="button add-and-filter gh-has-tooltip ui-sortable-handle">
                <span class="dashicons dashicons-plus-alt2"></span>AND
                <div class="gh-tooltip right">' . esc_html__( 'Add a filter', 'groundhogg' ) . '</div>
                </button>

                <button class="button remove-block gh-has-tooltip ui-sortable-handle">
                ' . esc_html__( 'Remove block', 'groundhogg' ) . '
                </button>
                </div>

                </div>
            </fieldset>';
        }

        /*
        * Returns HTML code for single AND condition. The indexes and values for specific fields should be replaced with sprintf.
        * Is used for initial building of the fields, and for adding more AND conditions
        *
        * @return  string
        */
        private function and_condition_html(): string {
            $comparing_options = $this->comparing_options();

            $option_elements = array_map( function( $value, $option_data ) {
                return sprintf( '<option value="%1s" class="%2s">%3s</option>', esc_attr( $value ), esc_attr( $option_data['class'] ), esc_html( $option_data['title'] ) );
            }, array_keys( $comparing_options ), array_values( $comparing_options ) );

            return
                '<div class="and-condition gh-condition" data-and-number="%1$s">

                <button class="remove-filter gh-has-tooltip ui-sortable-handle">
                <span class="dashicons dashicons-minus"></span>
                <div class="gh-tooltip right">' . esc_html__( 'Remove a filter', 'groundhogg' ) . '</div>
                </button>

                <input class="gh-meta-data gh-meta-field" list="gh-meta-fields" name="%3$s[%2$s][%1$s][field-name]" value="%4$s" required />

                <select class="gh-meta-data gh-meta-condition" name="%3$s[%2$s][%1$s][condition]" data-value="%5$s">
                <option value="" disabled selected>' . esc_html__( 'Select a condtion', 'groundhogg' ) . '</option>'
                . implode( '', $option_elements ) .
                '</select>

                <input class="gh-meta-data gh-meta-value" type="text" name="%3$s[%2$s][%1$s][field-value]" value="%6$s" />
                </div>';
        }

        /*
        * Builds all fields with AND conditions
        *
        * @param   $args (array) all conditions saved
        *
        * @return  string
        */
        private function render_and_conditions( array $args ): string {

            $acf_name = $args['field_name'] ?? false;
            if ( ! $acf_name || ! is_string( $acf_name ) ) {
                return '';
            }

            $condition = $args['condition'] ?? false;
            if ( empty( $condition ) || ! is_array( $condition ) ) {
                return '';
            }

            $field_value = $condition['field-value'] ?? '';
            if ( ! is_string( $field_value ) ) {
                return '';
            }

            $field_name = $condition['field-name'] ?? false;
            $esc_field_name = sanitize_text_field( strtolower( $field_name ) );
            if ( ! $esc_field_name || ! is_string( $esc_field_name ) ) {
            // if ( ! $field_name || !in_array( $field_name, $this->get_meta_fields() ) ) {
                return '';
            }

            $field_condition = $condition['condition'] ?? false;
            if ( ! $field_condition || !in_array( $field_condition, array_keys( $this->comparing_options() ) ) ) {
                return '';
            }

            $or_index  = absint ( $args['or_index'] ?? 0 );
            $and_index = absint ( $args['and_index'] ?? 0 );

            $single_condition_html = sprintf( $this->and_condition_html(),
                $and_index, $or_index, esc_attr( $acf_name ), esc_attr( $esc_field_name ), esc_attr( $field_condition ), esc_attr( $field_value ) );

            return $single_condition_html;
        }

        /*
        * Sanitizes the field value befor output or save in the database
        *
        * @param   $value (array) all conditions
        * @param   $function (string) the sanitizing function, esc_html or sanitize_text_field
        *
        * @return  array
        */
        private function sanitize( array $value, string $function ): array {
            if ( empty( $value ) || ! in_array( $function, [ 'esc_attr', 'sanitize_text_field' ], true ) ) {
                return [];
            }

            $result = [];
            foreach ( $value as $or_index => $or_block ) {
                $esc_or_index = absint($or_index);

                if ( ! is_array( $or_block ) ) {
                    return [];
                }

                foreach ( $or_block as $and_index => $and_condition ) {
                    if ( ! is_array( $and_condition ) ) {
                        return [];
                    }

                    $esc_and_index = absint($and_index);
                    foreach ( $and_condition as $and_key => $and_value ) {
                        $result[ $esc_or_index ][ $esc_and_index ][ call_user_func( $function, $and_key ) ] = call_user_func( $function, $and_value );
                    }
                }
            }
            return $result;
        }

        /**
        * Returns the value of the Contact field
        *
        * @param Contact $contact
        * @param string $field
        *
        * @return string
        */
        private function get_contact_field_value( $contact, string $field ): string {
            if ( ! \Groundhogg\is_a_contact( $contact ) ) {
                return '';
            }

            $method_map = [
                'full_name'    => 'get_full_name',
                'first_name'   => 'get_first_name',
                'last_name'    => 'get_last_name',
                'email'        => 'get_email',
                'date_created' => 'get_date_created',
                'age'          => 'get_age',
            ];

            if ( isset( $method_map[ $field ] ) && method_exists( $contact, $method_map[ $field ] ) ) {
                return $contact->{$method_map[ $field ]}() ?? '';
            }

            switch ( $field ) {
                case 'tags':
                    return '';

                case 'optin_status':
                    return Groundhogg\Preferences::get_preference_pretty_name( $contact->get_optin_status() ) ?? '';

                case 'owner':
                    return $contact->get_ownerdata()->user_nicename ?? '';

                default:
                    return $contact->get_meta( $field ) ?? '';
            }
        }

        /**
        * Returns true if the contact's value fits condition, and false if not
        *
        * @param string $condition
        * @param string $contact_field_value
        * @param string $value
        *
        * @return boolean
        */
        private function check_condition( string $condition, string $contact_field_value, string $value ): bool {
            switch ( $condition ) {
                case 'equal to':
                    return $contact_field_value == $value;

                case 'not equal to':
                    return $contact_field_value != $value;

                case 'contains':
                    return str_contains( $contact_field_value, $value );

                case 'does not contain':
                    return ! str_contains( $contact_field_value, $value );

                case 'is empty':
                    return empty( $contact_field_value );

                case 'is not empty':
                    return ! empty( $contact_field_value );

                default:
                    return false;
            }
        }

        /*
        * Checks the single condition
        *
        * @param   $condition (array) the condition like [ 'field_name' => (name), 'condition' => (condition), 'field_value' => (value) ]
        * @param   $contact (Contact)  the contact
        *
        * @return  bool
        */
        private function check( array $condition, $contact ): bool {
            if ( empty( $condition ) || ! \Groundhogg\is_a_contact( $contact ) ) {
            return false;
                }

            $field_name = $condition['field-name'] ?? false;
            if ( ! $field_name || ! in_array( $field_name, $this->get_meta_fields() ) ) {
              return false;
            }

            $field_condition = $condition['condition'] ?? false;
            if ( ! $field_condition || ! in_array( $field_condition, array_keys( $this->comparing_options() ) ) ) {
               return false;
            }

            $field_value = $condition['field-value'] ?? '';
            $esc_field_value = sanitize_text_field( strtolower( $field_value ) );

            if ( 'tags' === $field_name ) {
                $has_tag = $contact->has_tag( $esc_field_value );

                if ( 'has' == $field_condition xor $has_tag ) {
                    return false;
                }
            } else {
                $contact_field_value = sanitize_text_field( strtolower( $this->get_contact_field_value( $contact, $field_name ) ) );
                return $this->check_condition( $field_condition, $contact_field_value, $esc_field_value );
            }
            return true;
        }

        /*
        * Checks the AND conditions
        *
        * @param   $conditions (array) the conditions
        * @param   $contact (Contact)  the contact
        *
        * @return  bool
        */
        private function check_and( array $conditions, $contact ): bool {
            if ( empty( $conditions ) ) {
                return true;
            }

            if ( ! \Groundhogg\is_a_contact( $contact ) ) {
                return false;
            }

            foreach ( $conditions as $condition ) {
                if ( ! $this->check( $condition, $contact ) ) {
                    return false;
                }
            }
            return true;
        }

        /*
        * Builds datalist from the accessible meta fields. This in a single datalist used for all selectors of all fields on the page
        */
        function add_datalist(): void {
            $meta_fields = $this->get_meta_fields();

            $option_elements = array_map( function( string $meta_field ) {
                return sprintf( '<option value="%s">', esc_attr( $meta_field ) );
            }, $meta_fields );

            ob_start();
            ?>
                <datalist id="gh-meta-fields">
                <?php echo implode( '', $option_elements ); ?>
                </datalist>
            <?php
            echo ob_get_clean();
        }

        /*
        * Renders styles of all elements for the given ACF
        */
        function render_css(): void {
            ob_start();
            ?>
                <style>
                .logic-include-filters {
                    padding: 20px;
                    border: 1px solid #6c19ad;
                    border-radius: 10px;
                    position: relative;
                }

                .logic-include-filters .search-filters-editor .group {
                    flex-direction: column;
                }

                .add-or-filter {
                    position: absolute;
                    bottom: -34px;
                    display: flex !important;
                    place-items: center;
                    padding: 4px;
                }

                .add-remove-filter-container {
                    display: flex;
                    justify-content: space-between;
                    padding: 0.5em 0;
                }

                .button.add-and-filter.gh-has-tooltip {
                    display: flex;
                    place-items: center;
                    width: max-content;
                }

                .or-condition {
                    border-top: 1px solid #000;
                    position: relative;
                    padding: 12px 0;
                }

                .or-condition legend {
                    padding: 0 4px;
                    text-align: center;
                }

                .or-condition:first-of-type {
                    border-top: none;
                }

                .or-condition:first-of-type .remove-block,
                .or-condition:first-of-type legend {
                    display: none;
                }

                .gh-condition {
                    display: flex;
                    gap: 4px;
                }

                .search-filters-editor button.remove-block {
                    text-transform: uppercase;
                    max-width: fit-content;
                }

                .search-filters-editor button.remove-block:hover {
                    background: lightgray;
                }

                .gh-meta-field,
                .acf-field input[type=text].gh-meta-value {
                    width: 240px;
                }

                .acf-field select.gh-meta-condition {
                    width: 160px;
                }

                .or-condition:last-of-type {
                    padding-bottom: 0;
                }
                </style>
            <?php
            echo ob_get_clean();
        }

        /*
        * Renders scripts of all elements for the given ACF
        */
        function render_js(): void {
            $single_or_condition  = sprintf( $this->or_condition_html(), '${orNumber}', '' );
            $single_and_condition = sprintf( $this->and_condition_html(), '${andNumber}', '${orNumber}', '${fieldName}', '', '', '' );

            ob_start();
            ?>
                <script>
                (function($) {
                    $(document).ready(function() {

                        $('.gh-condition').each(function() {
                            const $self = $(this);
                            const $fieldName = $self.find('.gh-meta-field');
                            const $conditionSelector = $self.find('.gh-meta-condition');

                            const conditionValue = $conditionSelector.data('value');

                            showConditions( $fieldName, $conditionSelector );
                            $conditionSelector.find(`option[value="${conditionValue}"]`).attr('selected','selected');
                            showValue( conditionValue, $self.find('.gh-meta-value') );
                        });

                        const $logicElements = $('.logic-include-filters');

                        $logicElements.on('change', '.gh-meta-field', function() {
                            const $self = $(this);
                            const $conditionSelector = $self.closest('.gh-condition').find('.gh-meta-condition');

                            $conditionSelector.val('');
                            showConditions( $self, $conditionSelector );
                        });

                        $logicElements.on('change', '.gh-meta-condition', function() {
                            const $self = $(this);
                            const $fieldValue = $self.closest('.gh-condition').find('.gh-meta-value');

                            showValue( $self.val(), $fieldValue );
                        });

                        $logicElements.on('click', '.add-and-filter', function(e) {
                            e.preventDefault();
                            addAndCondition( $(this) );
                        });

                        $logicElements.on('click', '.remove-block', function(e) {
                            e.preventDefault();
                            $(this).closest('.or-condition').remove();
                        });

                        $logicElements.on('click', '.add-or-filter', function(e) {
                            e.preventDefault();
                            addOrCondition( $(this) );
                        });

                        $logicElements.on('click', '.remove-filter', function(e) {
                            $(this).closest('.gh-condition').remove();
                        });
                    });

                    function showConditions( $fieldName, $conditionSelector ) {
                        if ( 'tags' === $fieldName.val() ) {
                            $conditionSelector.find('.meta-field-options').hide();
                            $conditionSelector.find('.tag-options').show();
                        } else {
                            $conditionSelector.find('.tag-options').hide();
                            $conditionSelector.find('.meta-field-options').show();
                        }
                    }

                    function showValue( condition, $fieldValue ) {
                        if ( ['is empty', 'is not empty'].includes( condition ) ) {
                            $fieldValue.hide();
                        } else {
                            $fieldValue.show();
                        }
                    }

                    function addAndCondition( $button ) {
                        const $andElements = $button.closest('.group').find('.and-condition');

                        const fieldName = $button.closest('.logic-include-filters').attr('id');
                        const orNumber  = $button.closest('.or-condition').data('or-number');
                        const andNumber  = $andElements.length ? $andElements.last().data('and-number') + 1 : 1;

                        $button.parent().before( `<?php echo $single_and_condition ?>` );
                    }

                    function addOrCondition( $button ) {
                        const orNumber = $button.closest('.logic-include-filters').find('.or-condition:last-of-type').data('or-number') + 1;

                        $button.before( `<?php echo $single_or_condition ?>` );
                    }
                })(jQuery);
            </script>
            <?php
            echo ob_get_clean();
        }

        /*
        * Create the HTML for your field in the WordPress editor.
        *
        * @param   $field (array) the ACF being rendered
        */
        function render_field( array $field ): void {
            if ( empty( $field ) || empty( $field['name'] ) ) {
                return;
            }

            if ( ! isset( $field['value'] ) || ! is_array( $field['value'] ) || empty( $field['value'] ) ) {
                $field['value'] = [ '1' => [] ];
            }

            $esc_field_name = esc_attr( $field['name'] );
            ob_start();
            ?>
                <div class="logic-include-filters" id="<?php echo $esc_field_name ?>">
                    <?php
                        foreach ( $field['value'] as $or_index => $or_condition ) {

                            if ( empty( $or_condition ) || !is_array( $or_condition ) ) {
                                continue;
                            }

                            $and_condition_elements = [];

                            foreach ( $or_condition as $and_index => $and_condition ) {
                                $and_condition_elements[] = $this->render_and_conditions( [
                                    'field_name' => $esc_field_name,
                                    'or_index'   => $or_index,
                                    'and_index'  => $and_index,
                                    'condition'  => $and_condition,
                                ] );
                            }

                            $and_condition_html = implode( '', $and_condition_elements );
                            echo sprintf( $this->or_condition_html(), $or_index, $and_condition_html );
                        }
                    ?>
                    <button class="button add-or-filter gh-has-tooltip ui-sortable-handle">
                        <span class="dashicons dashicons-plus-alt2"></span>OR
                        <div class="gh-tooltip right">
                            <?php esc_html_e( 'Add a filter', 'groundhogg' ); ?>
                        </div>
                    </button>
                </div>
            <?php
            echo ob_get_clean();
        }

        /*
        * This filter is applied to the `$value` after it is loaded from the database.
        *
        * @return  array
        */
        function load_value( array $value, $post_id, $field ): array {
            if ( empty( $value ) ) {
                return $this->defaults['default_value'];
            }

            return $this->sanitize( $value, 'esc_attr' );
        }

        /*
        * This filter is applied to the `$value` before it is saved to the database.
        *
        * @return  array
        */
        function update_value( array $value, $post_id, $field ): array {
            if ( empty( $value ) ) {
                return $this->defaults['default_value'];
            }
            return $this->sanitize( $value, 'sanitize_text_field' );
        }

        /*
        * This filter is applied to the `$value` after it is loaded from the database and
        * before it is passed to the frontend via get_field().
        *
        * @return  bool
        */
        function format_value( array $value, $post_id, array $field ): bool {

            if ( empty( $field ) || empty( $value ) ) {
                return false;
            }

            $page = Groundhogg\get_url_var( 'page' );
            if ( 'gh_contacts' !== $page ) {
                return false;
            }

            $contact_id = absint( Groundhogg\get_url_var( 'contact' ) );
            if ( 0 === $contact_id ) {
                return false;
            }

            $contact = new \Groundhogg\Contact( $contact_id );
            if ( ! \Groundhogg\is_a_contact( $contact ) ) {
                return false;
            }

            foreach ( $value as $condition ) {
                if ( $this->check_and( $condition, $contact ) ) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
    * Registers the ACF field type of Condition logic
    *
    * @type    action
    *
    */
    add_action( 'init', 'gh_register_acf_condition_logic' );
    function gh_register_acf_condition_logic() {
        acf_register_field_type( 'Groundhogg_Condition_Logic' );
    }

endif;
