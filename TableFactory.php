<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Tactics\TableBundle;

use Tactics\TableBundle\Exception\TableException;
use Tactics\TableBundle\Exception\UnknownTypeException;
use Tactics\TableBundle\ColumnHeaderInterface;

/**
 * Description of TableFactory
 *
 * @author Gert Vrebos <gert.vrebos at tactics.be>
 */
class TableFactory implements TableFactoryInterface
{
   /**
    * {@inheritdoc
    */ 
    public function createBuilder($name, $type = '', array $options = array())
    {
        // todo resolving via dependency injection container
        
        $tableBuilderClass = "Tactics\\TableBundle\\Extension\\Builder\\" . \Symfony\Component\DependencyInjection\Container::camelize($type) . 'TableBuilder';
        
        if (! class_exists($tableBuilderClass))
        {
            throw new UnknownTypeException("TableBuilder type '" . $type . "' could not be resolved. (Guess was: $tableBuilderClass )");
        }
        
        // todo table type as option.. somehow
        return new $tableBuilderClass($name, '', $this, $options);
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function createTable($name, $type = '', array $options = array())
    {
        // todo resolving via dependency injection container
        
        $tableClass = "Tactics\\TableBundle\\Extension\\Table\\" . \Symfony\Component\DependencyInjection\Container::camelize($type) . 'Table';
        
        if (! class_exists($tableClass))
        {
            throw new UnknownTypeException("Table type '" . $type . "' could not be resolved. (Guess was: $tableClass )");
        }
        
        return new $tableClass($name, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createColumn($name, $type = '', ColumnHeaderInterface $columnHeader, array $options = array())
    {
        // todo resolving via dependency injection container
        
        $columnClass = "Tactics\\TableBundle\\Extension\\Type\\" . \Symfony\Component\DependencyInjection\Container::camelize($type) . 'Column';
        
        if (! class_exists($columnClass))
        {
            throw new UnknownTypeException("Column type '" . $type . "' could not be resolved. (Guess was: $columnClass )");
        }
        
        return new $columnClass($name, $columnHeader, $options);
    }

    
    /**
     * {@inheritdoc}
     */
    public function createColumnHeader($name, $type = '', array $options = array())
    {
        // todo resolving via dependency injection container
        
        $columnHeaderClass = "Tactics\\TableBundle\\Extension\\Type\\" . \Symfony\Component\DependencyInjection\Container::camelize($type) . 'ColumnHeader';
        
        if (! class_exists($columnHeaderClass))
        {
            throw new UnknownTypeException("ColumnHeader type '" . $type . "' could not be resolved. (Guess was: $columnHeaderClass )");
        }
        
        $type = new $columnHeaderClass($name, $options);
       
        return $type;
    }
    
}

