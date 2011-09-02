<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\PropelBundle\DataFixtures;

use \Propel;
use \BasePeer;
use \BaseObject;
use \ColumnMap;
use \PropelException;

use Propel\PropelBundle\Util\PropelInflector;

use Symfony\Component\Finder\Finder;

/**
 * Abstract class to manage a common logic to load datas.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
abstract class AbstractDataLoader implements DataLoaderInterface
{
    /**
     * @var string
     */
    protected $rootDir;
    /**
     * @var array
     */
    private $deletedClasses;
    /**
     * @var array
     */
    private $object_references;

    /**
     * Default constructor
     *
     * @param string $rootDir   The root directory.
     */
    public function __construct($rootDir)
    {
        $this->rootDir = $rootDir;
        $this->deletedClasses = array();
        $this->object_references = array();
    }

    /**
     * Transforms a file containing data in an array.
     *
     * @param string $file  A filename.
     * @return array
     */
    abstract protected function transformDataToArray($file);

    /**
     * @return string
     */
    public function getRootDir()
    {
        return $this->rootDir;
    }

    /**
     * {@inheritdoc}
     */
    public function load($files = array(), $connectionName)
    {
        $nbFiles = 0;

        // load map classes
        $this->loadMapBuilders();
        $this->dbMap = Propel::getDatabaseMap($connectionName);

        // wrap all database operations in a single transaction
        $this->con = Propel::getConnection($connectionName);

        try {
            $this->con->beginTransaction();

            foreach ($files as $file) {
                $datas = $this->transformDataToArray($file);
                $this->deleteCurrentData($datas);
                $this->loadDataFromArray($datas);

                $nbFiles++;
            }

            $this->con->commit();
        } catch (\Exception $e) {
            $this->con->rollBack();
            throw $e;
        }

        return $nbFiles;
    }

    /**
     * Deletes current data.
     *
     * @param array   $data  The data to delete
     */
    public function deleteCurrentData($data = null)
    {
        if ($data !== null) {
            $classes = array_keys($data);
            foreach (array_reverse($classes) as $class) {
                $class = trim($class);
                if (in_array($class, $this->deletedClasses)) {
                    continue;
                }

                // Check that peer class exists before calling doDeleteAll()
                $peerClass = constant($class.'::PEER');
                if (!class_exists($peerClass)) {
                    throw new \InvalidArgumentException(sprintf('Unknown class "%sPeer".', $class));
                }

                // bypass the soft_delete behavior if enabled
                $deleteMethod = method_exists($peerClass, 'doForceDeleteAll') ? 'doForceDeleteAll' : 'doDeleteAll';
                call_user_func(array($peerClass, $deleteMethod), $this->con);

                $this->deletedClasses[] = $class;
            }
        }
    }

    /**
     * Loads the data using the generated data model.
     *
     * @param array   $data  The data to be loaded
     */
    public function loadDataFromArray($data = null)
    {
        if ($data === null) {
            return;
        }

        foreach ($data as $class => $datas) {
            $class        = trim($class);
            $tableMap     = $this->dbMap->getTable(constant(constant($class.'::PEER').'::TABLE_NAME'));
            $column_names = call_user_func_array(array(constant($class.'::PEER'), 'getFieldNames'), array(BasePeer::TYPE_FIELDNAME));

            // iterate through datas for this class
            // might have been empty just for force a table to be emptied on import
            if (!is_array($datas)) {
                continue;
            }

            foreach ($datas as $key => $data) {
                // create a new entry in the database
                if (!class_exists($class)) {
                    throw new \InvalidArgumentException(sprintf('Unknown class "%s".', $class));
                }

                $obj = new $class();

                if (!$obj instanceof BaseObject) {
                    throw new \RuntimeException(sprintf('The class "%s" is not a Propel class. This probably means there is already a class named "%s" somewhere in symfony or in your project.', $class, $class));
                }

                if (!is_array($data)) {
                    throw new \InvalidArgumentException(sprintf('You must give a name for each fixture data entry (class %s).', $class));
                }

                foreach ($data as $name => $value) {
                    if (is_array($value) && 's' == substr($name, -1)) {
                        // many to many relationship
                        $this->loadManyToMany($obj, substr($name, 0, -1), $value);
                        continue;
                    }

                    $isARealColumn = true;
                    if ($tableMap->hasColumn($name)) {
                        $column = $tableMap->getColumn($name);
                    } else if ($tableMap->hasColumnByPhpName($name)) {
                        $column = $tableMap->getColumnByPhpName($name);
                    } else {
                        $isARealColumn = false;
                    }

                    // foreign key?
                    if ($isARealColumn) {
                        if ($column->isForeignKey() && null !== $value) {
                            $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
                            if (!isset($this->object_references[$relatedTable->getPhpName().'_'.$value])) {
                                throw new \InvalidArgumentException(sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedTable->getPhpName()));
                            }
                            $value = $this->object_references[$relatedTable->getPhpName().'_'.$value]->getByName($column->getRelatedName(), BasePeer::TYPE_COLNAME);
                        }
                    }

                    if (false !== $pos = array_search($name, $column_names)) {
                        $obj->setByPosition($pos, $value);
                    }
                    elseif (is_callable(array($obj, $method = 'set'.ucfirst(PropelInflector::camelize($name))))) {
                        $obj->$method($value);
                    } else {
                        throw new \InvalidArgumentException(sprintf('Column "%s" does not exist for class "%s".', $name, $class));
                    }
                }

                $obj->save($this->con);

                // save the object for future reference
                if (method_exists($obj, 'getPrimaryKey')) {
                    $class_default = constant(constant($class.'::PEER').'::CLASS_DEFAULT');
                    if ('/' !== substr($class_default, 0, 1)) {
                        $class_default = '/' . $class_default;
                    }
                    $this->object_references[Propel::importClass($class_default).'_'.$key] = $obj;
                }
            }
        }
    }

    /**
     * Loads many to many objects.
     *
     * @param BaseObject $obj           A Propel object
     * @param string $middleTableName   The middle table name
     * @param array $values             An array of values
     */
    protected function loadManyToMany($obj, $middleTableName, $values)
    {
        $middleTable = $this->dbMap->getTable($middleTableName);
        $middleClass = $middleTable->getPhpName();

        foreach ($middleTable->getColumns() as $column) {
            if ($column->isForeignKey() && constant(constant(get_class($obj).'::PEER').'::TABLE_NAME') != $column->getRelatedTableName()) {
                $relatedClass = $this->dbMap->getTable($column->getRelatedTableName())->getPhpName();
                break;
            }
        }

        if (!isset($relatedClass)) {
            throw new \InvalidArgumentException(sprintf('Unable to find the many-to-many relationship for object "%s".', get_class($obj)));
        }

        $setter = 'set'.get_class($obj);
        $relatedSetter = 'set'.$relatedClass;

        foreach ($values as $value) {
            if (!isset($this->object_references[$relatedClass.'_'.$value])) {
                throw new \InvalidArgumentException(sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedClass));
            }

            $middle = new $middleClass();
            $middle->$setter($obj);
            $middle->$relatedSetter($this->object_references[$relatedClass.'_'.$value]);
            $middle->save();
        }
    }

    /**
     * Loads all map builders.
     */
    protected function loadMapBuilders()
    {
        $dbMap  = Propel::getDatabaseMap();

        $finder = new Finder();
        $files  = $finder->files()->name('*TableMap.php')->in($this->getRootDir() . '/../');

        foreach ($files as $file) {
            $omClass = basename($file, 'TableMap.php');
            if (class_exists($omClass) && is_subclass_of($omClass, 'BaseObject')) {
                $tableMapClass = basename($file, '.php');
                $dbMap->addTableFromMapClass($tableMapClass);
            }
        }
    }
}
