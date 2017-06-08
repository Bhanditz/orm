<?php declare(strict_types = 1);

/**
 * @testCase
 * @dataProvider ../../../sections.ini
 */

namespace NextrasTests\Orm\Integration\Collection;

use Nextras\Orm\Collection\ArrayCollection;
use NextrasTests\Orm\CustomFunctions;
use NextrasTests\Orm\DataTestCase;
use NextrasTests\Orm\Helper;
use Tester\Assert;
use Tester\Environment;


$dic = require_once __DIR__ . '/../../../bootstrap.php';


class CollectionCustomFunctionsTest extends DataTestCase
{
	public function testLike()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$count = $this->orm->books->findBy([CustomFunctions::LIKE, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $this->orm->books->findBy([CustomFunctions::LIKE, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $this->orm->books->findBy([CustomFunctions::LIKE, 'title', 'Book X'])->count();
		Assert::same(0, $count);
	}


	public function testLikeArray()
	{
		if ($this->section === Helper::SECTION_ARRAY) {
			Environment::skip('Test only DbalMapper');
		}

		$collection = new ArrayCollection(iterator_to_array($this->orm->books->findAll()), $this->orm->books);

		$count = $collection->findBy([CustomFunctions::LIKE, 'title', 'Book'])->count();
		Assert::same(4, $count);

		$count = $collection->findBy([CustomFunctions::LIKE, 'title', 'Book 1'])->count();
		Assert::same(1, $count);

		$count = $collection->findBy([CustomFunctions::LIKE, 'title', 'Book X'])->count();
		Assert::same(0, $count);
	}
}


$test = new CollectionCustomFunctionsTest($dic);
$test->run();
