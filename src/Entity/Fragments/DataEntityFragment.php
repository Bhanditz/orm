<?php

/**
 * This file is part of the Nextras\ORM library.
 *
 * @license    MIT
 * @link       https://github.com/nextras/orm
 * @author     Jan Skrasek
 */

namespace Nextras\Orm\Entity\Fragments;

use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\IProperty;
use Nextras\Orm\Entity\IPropertyContainer;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Entity\Reflection\PropertyMetadata;
use Nextras\Orm\Entity\ToArrayConverter;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Relationships\IRelationshipCollection;
use Nextras\Orm\Relationships\IRelationshipContainer;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\InvalidArgumentException;
use Nextras\Orm\InvalidStateException;


abstract class DataEntityFragment extends RepositoryEntityFragment implements IEntity
{
	/** @var EntityMetadata */
	protected $metadata;

	/** @var array */
	private $data = [];

	/** @var array */
	private $validated = [];

	/** @var array */
	private $modified = [];

	/** @var mixed */
	private $persistedId = NULL;


	public function __construct()
	{
		parent::__construct();
		$this->modified[NULL] = TRUE;
		$this->metadata = $this->createMetadata();
	}


	public function getMetadata()
	{
		return $this->metadata;
	}


	public function isModified($name = NULL)
	{
		if ($name === NULL) {
			return (bool) $this->modified;
		}

		$this->metadata->getProperty($name); // checks property existence
		return isset($this->modified[NULL]) || isset($this->modified[$name]);
	}


	public function setAsModified($name = NULL)
	{
		$this->modified[$name] = TRUE;
		return $this;
	}


	public function isPersisted()
	{
		return $this->persistedId !== NULL;
	}


	public function getPersistedId()
	{
		return $this->persistedId;
	}


	public function setValue($name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		if ($metadata->isReadonly) {
			throw new InvalidArgumentException("Property '$name' is read-only.");
		}

		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function setReadOnlyValue($name, $value)
	{
		$metadata = $this->metadata->getProperty($name);
		$this->internalSetValue($metadata, $name, $value);
		return $this;
	}


	public function & getValue($name)
	{
		$property = $this->metadata->getProperty($name);
		return $this->internalGetValue($property, $name);
	}


	public function hasValue($name)
	{
		if (!$this->metadata->hasProperty($name)) {
			return FALSE;
		}

		return $this->internalHasValue($name);
	}


	public function & getRawValue($name)
	{
		$this->metadata->getProperty($name); // checks property existence

		if (!isset($this->data[$name])) {
			return NULL;
		} elseif ($this->data[$name] instanceof IProperty) {
			$value = $this->data[$name]->getRawValue();
			return $value;
		} else {
			return $this->data[$name];
		}
	}


	public function getProperty($name)
	{
		$propertyMetadata = $this->metadata->getProperty($name);
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		return $this->data[$name];
	}


	public function toArray($mode = self::TO_ARRAY_RELATIONSHIP_AS_IS)
	{
		return ToArrayConverter::toArray($this, $mode);
	}


	public function __clone()
	{
		$id = $this->getValue('id');
		foreach ($this->getMetadata()->getStorageProperties() as $property) {
			// getValue loads data & checks for not null values
			if ($this->getValue($property) && is_object($this->data[$property])) {
				if ($this->data[$property] instanceof IRelationshipCollection) {
					$data = iterator_to_array($this->data[$property]->get());
					$this->setValue('id', NULL);
					$this->data[$property] = clone $this->data[$property];
					$this->data[$property]->setParent($this);
					$this->data[$property]->set($data);
					$this->setValue('id', $id);

				} elseif ($this->data[$property] instanceof IRelationshipContainer) {
					$this->data[$property] = clone $this->data[$property];
					$this->data[$property]->setParent($this);

				} else {
					$this->data[$property] = clone $this->data[$property];
				}
			}
		}
		$this->setValue('id', NULL);
		$this->persistedId = NULL;
		parent::__clone();
	}


	public function serialize()
	{
		return [
			'modified' => $this->modified,
			'validated' => $this->validated,
			'data' => $this->toArray(IEntity::TO_ARRAY_RELATIONSHIP_AS_ID),
			'persistedId' => $this->persistedId,
		];
	}


	public function unserialize($unserialized)
	{
		$this->persistedId = $unserialized['persistedId'];
		$this->modified = $unserialized['modified'];
		$this->validated = $unserialized['validated'];
		$this->data = $unserialized['data'];
	}


	public function __debugInfo()
	{
		return $this->data;
	}


	// === events ======================================================================================================


	protected function onLoad(IRepository $repository, EntityMetadata $metadata, array $data)
	{
		parent::onLoad($repository, $metadata, $data);
		$this->metadata = $metadata;
		foreach ($metadata->getStorageProperties() as $property) {
			if (isset($data[$property])) {
				$this->data[$property] = $data[$property];
			}
		}

		$this->persistedId = $this->getId();
	}


	protected function onAttach(IRepository $repository, EntityMetadata $metadata)
	{
		parent::onAttach($repository, $metadata);
		$this->metadata = $metadata;
	}


	protected function onPersist($id)
	{
		parent::onPersist($id);
		$this->setId($id);
		$this->persistedId = $this->getId();
		$this->modified = [];
	}


	protected function onAfterRemove()
	{
		parent::onAfterRemove();
		$this->persistedId = NULL;
		$this->modified = [];
	}


	// === internal implementation =====================================================================================


	protected function createMetadata()
	{
		return MetadataStorage::get(get_class($this));
	}


	private function internalSetValue(PropertyMetadata $metadata, $name, $value)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($metadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			$this->data[$name]->setInjectedValue($value);
			return;
		}

		if ($metadata->hasSetter) {
			$value = call_user_func([$this, 'set' . $name], $value);
			if ($value === IEntity::SKIP_SET_VALUE) {
				$value = $this->data[$name];
			}
		}
		if (!$metadata->isValid($value)) {
			$class = get_class($this);
			throw new InvalidArgumentException("Value for {$class}::\${$name} property is invalid.");
		}
		$this->data[$name] = $value;
		$this->modified[$name] = TRUE;
	}


	private function & internalGetValue(PropertyMetadata $propertyMetadata, $name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($propertyMetadata, $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->getInjectedValue();
		}

		if ($propertyMetadata->hasGetter) {
			$value = call_user_func([$this, 'get' . $name], $this->data[$name]);
		} else {
			$value = $this->data[$name];
		}
		if (!isset($value) && !$propertyMetadata->isNullable) {
			$class = get_class($this);
			throw new InvalidStateException("Property {$class}::\${$name} is not set.");
		}
		return $value;
	}


	private function internalHasValue($name)
	{
		if (!isset($this->validated[$name])) {
			$this->initProperty($this->metadata->getProperty($name), $name);
		}

		if ($this->data[$name] instanceof IPropertyContainer) {
			return $this->data[$name]->hasInjectedValue();

		} else {
			return isset($this->data[$name]);
		}
	}


	private function initProperty(PropertyMetadata $propertyMetadata, $name)
	{
		$this->validated[$name] = TRUE;

		if (!array_key_exists($name, $this->data)) {
			$this->data[$name] = $propertyMetadata->defaultValue;
		}

		if ($propertyMetadata->container) {
			$class = $propertyMetadata->container;

			/** @var IProperty $property */
			$property = new $class($this, $propertyMetadata);
			$property->onModify(function() use ($name) {
				$this->modified[$name] = TRUE;
			});

			if ($this->isPersisted()) {
				$property->setRawValue($this->data[$name]);
			}

			$this->data[$name] = $property;

		} elseif ($this->data[$name] !== NULL) {
			$this->internalSetValue($propertyMetadata, $name, $this->data[$name]);
			unset($this->modified[$name]);
		}
	}

}
