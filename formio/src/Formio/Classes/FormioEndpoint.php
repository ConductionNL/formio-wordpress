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
        // $this->GFAPI = new WPS_Extend_Plugin('gravityforms/gravityforms.php', __FILE__, '*', 'my-plugin-text-domain');
    }

    private function load_hooks(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('conduction/v1', '/form/(?P<id>\d+)', array(
                'methods' => 'GET',
                'callback' => [$this, 'gravityFormToFormIO'],
            ));
        });
    }

    /**
     * Get user created Gravity Form and parses it to a form.io json form!
     *
     * @param array           $data   Options for the function.
     * @return array|string           form.io json definition
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

        // return $gForm;

        // Check if Gravity Form is founds
        if (!isset($gForm) || !$gForm) {
            return [
                'message' => 'Gravity Form with id: ' . $data['id'] . ' is not installed',
                'data' => $data['id']
            ];
        }

        $formIOArray['display'] = 'form';
        $formIOArray['components'] = $this->createFormIOComponents($gForm);
        isset($gForm['button']) && $formIOArray['components'][] = $this->createFormIOSubmitButton($gForm['button']);

        return $formIOArray;
    }

    private function createFormIOComponents(array $form): array
    {
        $components = [];

        if (isset($form['fields'])) {
            foreach ($form['fields'] as $field) {
                $components[] = [
                    'input' => true,
                    'label' => $field['label'],
                    'type' => $field['type'] === 'text' ? 'textfield' : $field['type'],
                    'key' => $field['adminLabel'] ?? $field['label'],
                    'id' => $field['id'] ?? null,
                    'size' => $this->getComponentSize($field['size']),
                    'description' => $field['description'],
                    'hidden' => $field['visibility'] === 'visible' ? false : true,
                    'validation' => [
                        'required' => $field['isRequired']
                    ]
                ];
            }
        }

        return $components;
    }

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
            'label'            => $button['submit'],
            'input'            => 'true',
        ];
    }

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
}
