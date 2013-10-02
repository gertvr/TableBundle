<?php

namespace Tactics\TableBundle\QueryBuilderFilter;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use Tactics\TableBundle\Form\Type\QueryBuilderFilterType;
use Tactics\myDate\myDate;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Comparison;

class QueryBuilderFilter implements QueryBuilderFilterInterface
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
     * @var $values array The filter values
     */
    protected $values = array();

    /**
     * @var array the default values
     */
    protected $default_values;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container, $defaultValues = array()) {
        $this->container = $container;
        $this->default_values = $defaultValues;
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
    public function execute(QueryBuilder $qb, $key = null, $options = array())
    {
        $this->retrieveFilterFromSessionOrDefaultOne($key);
        $this->filter($qb, $options);

        return $qb;
    }

    private function retrieveFilterFromSessionOrDefaultOne($key)
    {
        if(count($this->retrieveFilterFromSession($key)) === 0 && !$this->values) {
            $this->values = $this->getDefaultValues();
            return $this->getDefaultValues();
        } else {
            return $this->retrieveFilterFromSession($key);
        }
    }

    private function getDefaultValues()
    {
        return $this->default_values;
    }

    private function getAlias(QueryBuilder $qb, $fieldName = null)
    {
        // @todo support multiply entities.
        $aliases = $qb->getRootAliases();
        $alias = $aliases[0];

        return $fieldName ? $alias . '.' . $fieldName : $alias;
    }

    /**
     * Returns the current value of the field.
     *
     * @param type $name
     */
    public function get($name, $suffix = '')
    {
        if (! isset($this->fields[$name]))
        {
            return null;
        }

        return isset($this->values[$this->fields[$name]['form_field_name'] . $suffix]) ? $this->values[$this->fields[$name]['form_field_name'] . $suffix] : null;
    }

    /**
     * Adds a field to the fields array.
     *
     * @param $name    string The name of the filter
     * @param $options array  Additional options.
     *
     * @return $this QueryBuilderFilter The QueryBuilder instance.
     */
    public function add($name, array $options = array())
    {
        $resolver = new OptionsResolver();
        $this->setDefaultOptions($resolver);
        $options = $resolver->resolve($options);

        if (! isset($options['form_field_name']))
        {
            // Replace '.' to '__' because '.' is not allowed in a post request.
            $options['form_field_name'] = str_replace('.', '__', $name);
        }

        if (! isset($options['label']))
        {
            $label = $name;

            // propel field: strip table name
            if (strpos($label, '.'))
            {
                $label = substr($label, strpos($label, '.') + 1);
            }

            // propel field: remove _id postfix
            if (strpos($label, '_ID') !== false)
            {
                $label = substr($label, 0, strpos($label, '_ID'));
            }

            // humanize
            $label = ucfirst(strtolower(str_replace('_', ' ', $label)));

            $options['label'] = $label;
        }

        $this->fields[$name] = $options;

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
            ->createBuilder(new QueryBuilderFilterType());

        //dont loop the field but loop the fields mixed with the sessien values
        foreach ($this->fields as $fieldName => $options)
        {
            $value = isset($this->values[$fieldName]) ? $this->values[$fieldName] : null;

            $fieldOptions = array(
                'required' => false,
                'data' => $value,
                'label' => $options['label'],
                'render_optional_text' => false,
                'attr' => $options['attr'],
            );

            $formFieldName = $options['form_field_name'];

            // Prepare
            switch($options['type'])
            {
                case 'date':
                case 'datum':
                    if ($options['datum_from_and_to']){
                        $fieldOptions['data'] = $value ? \DateTime::createFromFormat('d/m/Y', $value) : null;
                        $fieldOptions['label'] = $options['label'] . ' from';
                        $builder->add($formFieldName . '_from', $options['type']);
                        $fieldOptions['label'] = $options['label'] . ' to';
                        $builder->add($formFieldName . '_to', $options['type']);
                        break;
                    }
                    else {
                        $fieldOptions['data'] = $value ? \DateTime::createFromFormat('d/m/Y', $value) : null;
                        $builder->add($formFieldName, $options['type']);
                        break;
                    }
                case 'date_time':
                    $fieldOptions['data'] = $value ? \DateTime::createFromFormat('d/m/Y h:i', $value) : null;
                    $fieldOptions['label'] = $options['label'] . ' from';
                    $builder->add($formFieldName . '_from', 'tactics_datetime');
                    $fieldOptions['label'] = $options['label'] . ' to';
                    $builder->add($formFieldName . '_to', 'tactics_datetime');
                    break;

                case 'choice':
                    $fieldOptions['choices'] = $options['choices'];
                    if (isset($options['multiple'])) {
                        $fieldOptions['multiple'] = $options['multiple'];
                    }
                    $builder->add($formFieldName, $options['type'], $fieldOptions);
                    break;
                case 'boolean':
                    $options['type'] = 'choice';
                    $fieldOptions['choices'] = array(0 => 'No', 1 => 'Yes');
                    $builder->add($formFieldName, $options['type'], $fieldOptions);
                    break;
                case 'checkbox':
                    $fieldOptions['data'] = (bool) $fieldOptions['data'];
                    $builder->add($formFieldName, $options['type'], $fieldOptions);
                    break;
                case 'entity':
                    if (isset($options['multiple'])) {
                        $fieldOptions['multiple'] = $options['multiple'];
                    }
                    $fieldOptions['class'] = $options['class'];
                    $fieldOptions['query_builder'] = $options['query_builder'];
                    $builder->add($formFieldName, $options['type'], $fieldOptions);
                default:
                    $builder->add($formFieldName, $options['type'], $fieldOptions);
                    break;
            }
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
                'comparison' => 'LIKE',
                'type'     => 'text',
                'value'    => null,
                'choices'  => null,
                'class' => null,
                'query_builder' => null,
                'datum_from_and_to' => true,
                'entire_day' => true,
                'attr' => array(),
        ));

        $resolver->setOptional(array('label', 'form_field_name', 'filter', 'multiple'));
    }

    public function getValues()
    {
        return $this->values;
    }

    public function buildFromType(QueryBuilderFilterTypeInterface $type)
    {
        $type->build($this);
    }

    /**
     * Retrieve or set filter options from the session.
     */
    private function retrieveFilterFromSession($key)
    {
        $request = $this->container->get('request');
        $session = $this->container->get('session');

        $key = null === $key ? 'filter/'.$request->attributes->get('_route') : $key;

        // Update fields and place them in the session.
        if ($request->getMethod() == 'POST' && $request->get('filter_by')) {
            $this->values = $request->get('filter_by');

            // Store current filter values in session
            $session->set($key, $this->values);
        }
        // User doesn't post, check if filter_by for this route exits in
        // session.
        else if ($session->has($key)) {
            // Retrieve and validate fields
            $this->values = $session->get($key);
        }
    }

    /**
     * Apply filter.
     */
    private function filter(QueryBuilder $qb, $options) {
        foreach ($this->fields as $fieldName => $options) {
            if (/*$this->get($fieldName) &&*/ ! $this->applyFilter($qb, $fieldName, $options)) {
                switch ($options['type']) {
                    case 'date':
                    case 'datum':
                        $this->addDateTimeToQueryBuilder($qb, 'd/m/Y' , $fieldName, $options['entire_day']);
                    break;
                    case 'date_time':
                        $this->addDateTimeToQueryBuilder($qb, 'd/m/Y h:i' , $fieldName, $options['entire_day']);
                    break;
                    case 'entity':
                        $value = $this->get($fieldName);
                        if ($value) {
                            $qb->andWhere(
                                $qb->expr()->eq(
                                    $this->getAlias($qb, $fieldName),
                                    ':'.$fieldName
                                )
                            )
                            ->setParameter($fieldName, $this->get($fieldName));
                            ;
                        }
                    break;

                    default:
                        $value = $this->get($fieldName);

                        if ($value) {
                            if (! isset($options['comparison'])) {
                                $qb->andWhere(
                                    $qb->expr()->eq(
                                        $this->getAlias($qb, $fieldName),
                                        ':'.$fieldName
                                    )
                                );
                            } else {
                                switch ($options['comparison']) {
                                    case 'LIKE':
                                        $qb->andWhere(
                                            $qb->expr()->like(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '=':
                                        $qb->andWhere(
                                            $qb->expr()->eq(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '<>':
                                        $qb->andWhere(
                                            $qb->expr()->neq(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '<':
                                        $qb->andWhere(
                                            $qb->expr()->lt(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '<=':
                                        $qb->andWhere(
                                            $qb->expr()->lte(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '>':
                                        $qb->andWhere(
                                            $qb->expr()->gt(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case '>=':
                                        $qb->andWhere(
                                            $qb->expr()->gte(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case 'IS NULL':
                                        $qb->andWhere(
                                            $qb->expr()->isNull(
                                                $this->getAlias($qb, $fieldName)
                                            )
                                        );
                                        break;
                                    case 'IS NOT NULL':
                                        $qb->andWhere(
                                            $qb->expr()->isNotNull(
                                                $this->getAlias($qb, $fieldName)
                                            )
                                        );
                                        break;
                                    case 'IN':
                                        $qb->andWhere(
                                            $qb->expr()->in(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                    case 'NOT IN':
                                        $qb->andWhere(
                                            $qb->expr()->notIn(
                                                $this->getAlias($qb, $fieldName),
                                                ':'.$fieldName
                                            )
                                        );
                                        break;
                                      case 'INSTANCE OF':
                                        $qb->andWhere(
                                            $this->getAlias($qb) . ' INSTANCE OF ' . $value
                                        );
                                        break;
                                    default:
                                        throw new \Exception('Unsupported comparison '.$options['comparison']);
                                        break;
                                }
                            }

                            if (isset($options['comparison']) && 'LIKE' === $options['comparison']) {
                                $qb->setParameter($fieldName, '%'.$value.'%');
                            } elseif (isset($options['comparison']) && 'INSTANCE OF' === $options['comparison']) {
                                // Nothing
                            } elseif (! isset($options['comparison']) || 'IS NULL' !== $options['comparison'] && 'IS NOT NULL' !== $options['comparison']) {
                                $qb->setParameter($fieldName, $value);
                            }
                        }
                        break;
                }
            }
        }
    }

    private function applyFilter(QueryBuilder $qb, $fieldName, $options) {
        if (! isset($options['filter'])) {
            return false;
        }

        if (null !== $this->get($fieldName) && '' !== $this->get($fieldName)) {
            $options['filter']($qb, $this->getAlias($qb), $fieldName, $this->get($fieldName));
        }

        return true;
    }

    private function addDateTimeToQueryBuilder($qb, $dateTimeFormat, $fieldName, $includeEntireDay)
    {
        $value = $this->get($fieldName, '_from');

        if ($value)
        {
            $dt = \DateTime::createFromFormat($dateTimeFormat, $value);
            //set the hour to 00:00:00 so result that have an hour defined earlier than this hour aren't lost
            if($includeEntireDay) {
                $dt->setTime(0,0,0);
            }

            $qb->andWhere(
                $qb->expr()->gte(
                    $this->getAlias($qb, $fieldName),
                    ':'.$fieldName.'_from'
                ))
                ->setParameter($fieldName.'_from', $dt);
        }

        $value = $this->get($fieldName, '_to');

        if ($value)
        {
            $dt = \DateTime::createFromFormat($dateTimeFormat, $value);
            //set the hour to 23:59:59 so result that have an hour defined earlier than this hour aren't lost
            if($includeEntireDay) {
                $dt->setTime(23,59,59);
            }

            $qb->andWhere(
                $qb->expr()->lte(
                    $this->getAlias($qb, $fieldName),
                    ':'.$fieldName.'_to'
                ))
                ->setParameter($fieldName.'_to', $dt);
        }
    }
}
