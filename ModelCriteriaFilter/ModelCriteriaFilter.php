<?php

namespace Tactics\TableBundle\ModelCriteriaFilter;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\Request;
use \ModelCriteria;
use \Criteria;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Tactics\TableBundle\Form\Type\ModelCriteriaFilterType;
use Tactics\myDate\myDate;

/**
 * @author Aaron Muylaert <aaron.muylaert at tactics.be>
 */
class ModelCriteriaFilter implements ModelCriteriaFilterInterface
{
    /**
      * @var $container ContainerInterface A ContainerInterface instance.
     */
    protected $container;

    /*
     * @var $fields array The filtered fields.
     */
    protected $fields = array();

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ModelCriteria $mc, $key = null, $options = array())
    {
        $request = $this->container->get('request');
        $session = $this->container->get('session');

        $filterBy = $request->get('filter_by');

        $key = null === $key ? 'filter/'.$request->attributes->get('_route') : $key;

        // Update fields and place them in the session.
        if ($request->getMethod() == 'POST' && $filterBy) {
            foreach ($filterBy as $postedFieldName => $value) {
              
                // todo find out what to do with _token.
                if ($postedFieldName === '_token') continue;

                // todo Exception classes.
                if (array_key_exists($postedFieldName, $this->fields) === false) continue;

                // Add to fields array.
                $this->fields[$postedFieldName]['value'] = $value;

                // Set fields array in session.
                $session->set($key, $this->fields);
            }
        // User doesn't post, check if filter_by for this route exits in 
        // session.
        } elseif ($session->has($key)) {
            // Retrieve and validate fields
            $this->fields = $session->get($key);

            $resolver = new OptionsResolver();
            $this->setDefaultOptions($resolver);

            foreach ($this->fields as $fieldName => $options) {
                $resolver->resolve($options);    
            }
        }
        
        // Add filter info to ModelCriteria.
        foreach ($this->fields as $fieldName => $options) {
            $fieldName = str_replace('__', '.', $fieldName);

            if ($options['value'] === NULL) continue;
            // Empty strings get posted.
            if ($options['value'] === '') {
                $this->options[$fieldName]['value'] = null;
                continue;
            }

            if (($options['type'] === 'datum' || $options['type'] === 'date') && $options['value']) {
              $dt = \DateTime::createFromFormat('d/m/Y', $options['value']);
              $options['value'] = $dt->format('Y-m-d');

              $fieldName = rtrim($fieldName, '_van _tot');
            }

            if ($options['criteria'] === Criteria::LIKE) {
                $options['value'] = '%'.$options['value'].'%';
            }

            $mc->addAnd($fieldName, $options['value'], $options['criteria']);
        }

        return $mc;
    }

    /**
     * Adds a field to the fields array.
     *
     * @param $field   string The name of the field.
     * @param $options array  Additional options.
     *
     * @return $this ModelCriteriaFilter The ModelCriteriaFilter instance.
     */
    public function add($field, array $options = array()) 
    {
        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);
        
        $options = $resolver->resolve($options);

        // Replace '.' to '__' because '.' is not allowed in a post request.
        $name = str_replace('.', '__', $field);

        $label = $this->getFieldLabel($name);

        if ($options['type'] === 'date' || $options['type'] === 'datum') {
            $this->fields[$name.'_van'] = $options; 
            $this->fields[$name.'_van']['label'] = $label.' van';
            $this->fields[$name.'_van']['criteria'] = Criteria::GREATER_EQUAL;
            $this->fields[$name.'_tot'] = $options; 
            $this->fields[$name.'_tot']['label'] = $label.' tot';
            $this->fields[$name.'_tot']['criteria'] = Criteria::LESS_EQUAL;
        }
        else {
            $this->fields[$name] = $options;
        }

        return $this;
    }

    /**
     * Builds a form based on the fields array.
     *
     * @return Form A Form instance.
     */
    public function getForm() 
    {
        $builder = $this->container->get('form.factory')
            ->createBuilder(new ModelCriteriaFilterType());

        foreach ($this->fields as $fieldName => $options)
        {
            $label = isset($options['label']) ? $options['label'] : $this->getFieldLabel($fieldName);
            if ($options['type'] === 'date' || $options['type'] === 'datum' && $options['value']) {
                $options['value'] = \DateTime::createFromFormat('d/m/Y', $options['value']);
            }

            $builder->add($fieldName, $options['type'], array(
              'required' => false,
              'data' => ($options['value'] ? $options['value'] : null),
              'label' => $label,
              'render_optional_text' => false 
               
            ));
        }

        return $builder->getForm();
    }

    /**
     * Sets the default options for this type.
     *
     * @param OptionsResolverInterface $resolver The resolver for the options.
     */
    private function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setDefaults(array(
                'criteria' => Criteria::LIKE,
                'type'     => 'text',
                'value'    => null
        ));

        $resolver->setOptional(array('label'));
    }

    /**
     * Transform fieldname to label.
     *
     * @return string
     */
    private function getFieldLabel($fieldName)
    {
      return ucfirst(strtolower(str_replace('_', ' ', mb_substr($fieldName, strpos($fieldName, '__')+1))));
    }
}