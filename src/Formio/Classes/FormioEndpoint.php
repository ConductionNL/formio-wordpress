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
            'customClass'      => 'utrecht-button'
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
     * Creates NL Design class for component
     * 
     * @param array $type     Field type 
     * @param array $required If this field is required or not 
     * @return array nl design className
     */
    private function getCustomClass(string $type, bool $required): string
    {
        $customClass = '';
        switch ($type) {
            case 'textfield':
                $customClass = 'utrecht-textbox utrecht-textbox--html-input';
                $required && $customClass .= ' utrecht-textbox--required';
                break;
            case 'textarea':
                $customClass = 'utrecht-textarea utrecht-textarea--html-textarea';
                $required && $customClass .= ' utrecht-textarea--required';
                break;
            case 'number':
                $customClass = 'utrecht-number utrecht-number--html-number';
                $required && $customClass .= ' utrecht-number--required';
                break;
            case 'select':
            case 'selectboxes':
                $customClass = 'utrecht-select utrecht-select--html-select';
                break;
            case 'checkbox':
                $customClass = 'utrecht-checkbox utrecht-checkbox--html-input';
                break;
            case 'radio':
                $customClass = 'utrecht-radio-button utrecht-radio-button--html-input';
                break;
            default:
                $customClass = 'utrecht-textbox utrecht-textbox--html-input';
                $required && $customClass .= ' utrecht-textbox--required';
                break;
        }

        return $customClass;
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

                $component['customClass'] = $this->getCustomClass($component['type'], $field['isRequired']);

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
    public function gf_to_formio($request)
    {
        // Check if Gravity Forms is installed
        if (!class_exists('GFAPI')) {
            return ['message' => 'Gravity Forms is not installed'];
        }

        // Check if id is given
        if (!isset($request['id'])) {
            return ['message' => 'No id given'];
        }

        // Get Gravity Form with id
        $gForm = \GFAPI::get_form($request['id']);

        // Check if Gravity Form is founds
        if (!isset($gForm) || !$gForm) {
            return [
                'message' => 'Gravity Form with id: ' . $request['id'] . ' is not found',
                'data' => $request['id']
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
    public function formio_post($request)
    {
        // Check if Gravity Forms is installed
        if (!class_exists('GFAPI')) {
            return ['message' => 'Gravity Forms is not installed'];
        }

        // Check if id is given
        if (!isset($request['id'])) {
            return ['message' => 'No id given'];
        } else {
            $id = $request['id'];
        }

        // Get form from Gravity Forms plugin
        $gForm = \GFAPI::get_form($id);

        // Check if Gravity Form is founds
        if (!isset($gForm) || !$gForm) {
            return [
                'message' => 'Gravity Form with id: ' . $id . ' is not found',
                'data' => $id
            ];
        }

        // Get body
        $postedBody = json_decode($request->get_body(), true);

        // Map incomming body to a gravity forms submit body
        $body = [];
        $iteratedIDs = [];
        foreach ($gForm['fields'] as $field) {
            foreach ($postedBody as $key => $value) {
                if (!in_array($field['id'], $iteratedIDs) && $key == $field['adminLabel']) {
                    $body['input_' . $field['id']] = $value;
                }
            }
        }

        $body['formId'] = $id;
        $result = \GFAPI::submit_form($id, $body);

        return $result;
    }

    /**
     * Loads/initializes wordpress actions and hooks  
     */
    private function load_hooks(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('owc/v1', '/gf-formio/(?P<id>\d+)', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'gf_to_formio'],
                ],
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'formio_post'],
                ]
            ]);
        });
    }
}
