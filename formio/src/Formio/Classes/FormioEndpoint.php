<?php

namespace OWC\Formio\Classes;

use OWC\Formio\Foundation\Plugin;

class FormioEndpoint
{
    /** @var Plugin */
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->load_hooks();
    }

    /**
     * Creates a standard form.io submit button 
     * 
     * @param array $button Gravity Forms field submit button
     * @return array         form.io submit button
     */
    private function createFormIOSubmitButton(array $button): array
    {
        return [
            'type'             => 'button',
            'theme'            => 'primary',
            'disableOnInvalid' => true,
            'action'           => 'submit',
            'rightIcon'        => '',
            'leftIcon'         => '',
            'size'             => 'md',
            'key'              => 'submit',
            'tableView'        => false,
            'label'            => $button['text'] ?? 'Submit',
            'input'            => 'true',
        ];
    }

    /**
     * Translates Gravity Forms field size to form.io component size
     * 
     * @param string $type Gravity Forms field size
     * @return string      form.io component size
     */
    private function getComponentSize(string $size): string
    {
        switch ($size) {
            case 'extra-small':
                return 'xs';
            case 'small':
                return 'sm';
            case 'large':
                return 'lg';
            case 'extra-large':
                return 'xl';
        }
    }

    /**
     * Translates Gravity Forms type to form.io type
     * 
     * @param string $type Gravity Forms field type
     * @return string      form.io component type
     */
    private function getComponentType(string $type): string
    {
        // If type itself is valid return that
        $alreadyValidTypes = ['textfield', 'number', 'checkbox', 'email', 'time', 'datetime'];
        if (in_array($type, $alreadyValidTypes)) {
            return $type;
        }

        // Check type and translate to formio type
        switch ($type) {
            case 'string':
            case 'text':
                return 'textfield';
            case 'int':
            case 'integer':
            case 'float':
                return 'number';
            case 'boolean':
            case 'consent':
                return 'checkbox';
            case 'date';
                return 'day';
            case 'phone':
                return 'phoneNumber';
            case 'multiselect':
                return 'select';
            default:
                return $type;
        }
    }

    /**
     * Adds choices from a gravity form field to the form.io component
     * 
     * @param array $component New form.io component 
     * @param array $choices Choices from Gravity Forms field 
     * @return array form.io component
     */
    private function setInputChoices(array $component, array $choices): array
    {
        $component['dataSrc'] = 'values';
        foreach ($choices as $choice) {
            $newValue = [
                'label' => $choice['text'],
                'value' => $choice['value']
            ];

            $component['type'] === 'radio' || $component['type'] === 'selectboxes' ?
                $component['values'][] = $newValue :
                $component['data']['values'][] = $newValue;

            $choice['isSelected'] && $component['defaultValue'] = $choice['value'];
        }

        return $component;
    }

    /**
     * Creates values for advanced form.io components
     * 
     * @param array $component Component we will add the values to
     * @param object $field     Gravity Forms field we will read the values from
     * @return array           form.io component
     */
    private function createAdvancedValues(array $component, object $field): array
    {
        // Multiple true for multiselect
        if ($field['type'] === 'consent') {
            $component['label'] = $field['checkboxLabel'];
        }


        // Multiple true for multiselect
        if ($field['type'] === 'multiselect') {
            $component['multiple'] = true;
        }

        // Type checkbox to selectboxes if there are choices
        if ($field['type'] === 'checkbox' && isset($field['choices'])) {
            $component['type'] = 'selectboxes';
        }

        // Set choices
        if (isset($field['choices']) && is_array($field['choices'])) {
            $component['widget'] = 'choicesjs';
            $component = $this->setInputChoices($component, $field['choices']);
        }

        return $component;
    }

    /**
     * Creates form.io componentes based on gravity form fields
     * 
     * @param array $form Gravity Forms form 
     * @return array form.io form array
     */
    private function createFormIOComponents(array $form): array
    {
        $components = [];

        if (isset($form['fields'])) {
            foreach ($form['fields'] as $field) {
                $component = [
                    'input' => true,
                    'label' => $field['label'],
                    'type' => $this->getComponentType($field['type']),
                    'key' => !empty($field['adminLabel']) ? $field['adminLabel'] : $field['label'],
                    'id' => $field['id'] ?? null,
                    'size' => $this->getComponentSize($field['size']),
                    'description' => $field['description'],
                    'hidden' => $field['visibility'] === 'visible' ? false : true,
                    'validation' => [
                        'required' => $field['isRequired']
                    ],
                    'widget' => [
                        'type' => 'input'
                    ],
                    'defaultValue' => $field['defaultValue']
                ];

                $components[] = $this->createAdvancedValues($component, $field);
            }
        }

        return $components;
    }



    /**
     * Get user created Gravity Form and translates it to a form.io form!
     *
     * @param array $data Options for the function.
     * @return array      form.io array
     */
    public function gravityFormToFormIO($data)
    {
        // Check if Gravity Forms is installed
        if (!class_exists('GFAPI')) {
            return ['message' => 'Gravity Forms is not installed'];
        }

        // Check if id is given
        if (!isset($data['id'])) {
            return ['message' => 'No id given'];
        }

        // Get Gravity Form with id
        $gForm = \GFAPI::get_form($data['id']);

        // Check if Gravity Form is founds
        if (!isset($gForm) || !$gForm) {
            return [
                'message' => 'Gravity Form with id: ' . $data['id'] . ' is not found',
                'data' => $data['id']
            ];
        }

        $formIOArray['display'] = 'form';
        $formIOArray['components'] = $this->createFormIOComponents($gForm);
        isset($gForm['button']) && $formIOArray['components'][] = $this->createFormIOSubmitButton($gForm['button']);

        return $formIOArray;
    }


    /**
     * Get form.io post and lets Gravity Forms handle it
     *
     * @param array $data Options for the function.
     * @return array      form.io array
     */
    public function formIOPost($data)
    {
        // @TODO
        return $data['id'];
    }

    /**
     * Loads/initializes wordpress actions and hooks  
     */
    private function load_hooks(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('owc/v1', '/gf-formio/(?P<id>\d+)', array(
                'methods' => 'GET',
                'callback' => [$this, 'gravityFormToFormIO'],
            ));
            register_rest_route('owc/v1', '/gf-formio/(?P<id>\d+)', array(
                'methods' => 'POST',
                'callback' => [$this, 'formIOPost'],
            ));
        });
    }
}
