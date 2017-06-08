<?php declare(strict_types = 1);

/**
 * This file is part of the Nextras\Orm library.
 * @license    MIT
 * @link       https://github.com/nextras/orm
 */

namespace Nextras\Orm\Mapper;

use Closure;
use Nette\Object;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\LogicException;
use Nextras\Orm\Repository\IRepository;
use Nextras\Orm\StorageReflection\IStorageReflection;
use Nextras\Orm\StorageReflection\StringHelper;


abstract class BaseMapper extends Object implements IMapper
{
	/** @var string */
	protected $tableName;

	/** @var IStorageReflection */
	protected $storageReflection;

	/** @var IRepository */
	private $repository;


	public function processArrayFunctionCall(ArrayCollectionHelper $helper, string $function, array $args): Closure
	{
		switch ($function) {
			case ICollection::AND:
				$callbacks = $helper->createCallbacks($args);
				return function ($value) use ($callbacks) {
					foreach ($callbacks as $callback) {
						if (!$callback($value)) return false;
					}
					return true;
				};

			case ICollection::OR:
				$callbacks = $helper->createCallbacks($args);
				return function ($value) use ($callbacks) {
					foreach ($callbacks as $callback) {
						if ($callback($value)) return true;
					}
					return false;
				};

			default:
				$methodName = 'processArrayFunction' . ucfirst($function);
				if (method_exists($this, $methodName)) {
					return $this->$methodName($helper, ...$args);

				} else {
					throw new LogicException("Call to unknown array function $function. $methodName" . get_class($this));
				}
		}
	}


	public function setRepository(IRepository $repository)
	{
		if ($this->repository && $this->repository !== $repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is already attached to repository.");
		}

		$this->repository = $repository;
	}


	public function getRepository(): IRepository
	{
		if (!$this->repository) {
			$name = get_class($this);
			throw new InvalidStateException("Mapper '$name' is not attached to repository.");
		}

		return $this->repository;
	}


	public function getTableName(): string
	{
		if (!$this->tableName) {
			$tableName = str_replace('Mapper', '', $this->getReflection()->getShortName());
			$this->tableName = StringHelper::underscore($tableName);
		}

		return $this->tableName;
	}


	public function getStorageReflection(): IStorageReflection
	{
		if ($this->storageReflection === null) {
			$this->storageReflection = $this->createStorageReflection();
		}

		return $this->storageReflection;
	}


	abstract protected function createStorageReflection();
}
