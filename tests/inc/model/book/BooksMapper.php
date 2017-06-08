<?php declare(strict_types = 1);

namespace NextrasTests\Orm;

use Closure;
use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\ICollection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Mapper;


final class BooksMapper extends Mapper
{
	public function findBooksWithEvenId(): ICollection
	{
		return $this->toCollection($this->builder()->where('id % 2 = 0'));
	}


	public function processQueryBuilderFunctionLike(QueryBuilder $builder, string $propertyExpr, string $value): array
	{
		$column = $this->getQueryBuilderHelper()->processPropertyExpr($builder, $propertyExpr);
		return ["$column LIKE %like_", $value];
	}


	public function processArrayFunctionLike(ArrayCollectionHelper $helper, string $propertyExpr, string $value): Closure
	{
		return $helper->createExpressionFilter2($propertyExpr, $value, function ($propertyValue, $value) {
			return Strings::startsWith($propertyValue, $value);
		});
	}
}
