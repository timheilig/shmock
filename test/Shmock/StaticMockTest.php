<?php

namespace Shmock;

require_once 'MockChecker.php';

/**
 * These tests must be run in separate processes due to the way that PHPUnit
 * generates mock class names. Since a failure in a prior test will get tracked
 * through to the next test case, we must run each in a separate process to prevent
 * collision.
 */
class StaticMockTest extends \PHPUnit_Framework_TestCase
{
    use MockChecker;

    protected function getPHPUnitStaticClass($clazz)
    {
        $this->staticClass = new PHPUnitStaticClass($this, $clazz);

        return $this->staticClass;
    }

    protected function getClassBuilderStaticClass($clazz)
    {
        $this->staticClass = new ClassBuilderStaticClass($this, $clazz);

        return $this->staticClass;

    }

    /**
     * @return callable these callables return instances of Instance that specialize
     * on returning mocked static classes.
     */
    public function instanceProviders()
    {
        return [
          // [[$this, "getPHPUnitStaticClass"]], // requires process isolation
          [[$this, "getClassBuilderStaticClass"]]
        ];
    }

    private function buildMockClass(callable $getClass, callable $setup)
    {
        $this->staticClass = $getClass("\Shmock\ClassToMockStatically");
        $setup($this->staticClass);

        return $this->staticClass->replay();
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanExpectMethodsBeInvoked(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_value(5);
        });

        $this->assertEquals(5, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testFrequenciesCanBeEnforced(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->times(2)->return_value(10);
        });

        $this->assertEquals(10, $mock::getAnInt());
        $this->assertEquals(10, $mock::getAnInt());

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::getAnInt();
        }, "the third invocation of getAnInt should have triggered the frequency check");

    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanHaveAnyNumberOfInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->any()->return_value(15);
        });

        // should not fail here
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testMockClassesCanHaveExactlyZeroInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->never();
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::getAnInt();
        }, "expected to never invoke the mock object");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testAtLeastOnceAllowsManyInvocations(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->at_least_once()->return_value(15);
        });

        $mock::getAnInt();
        $mock::getAnInt();
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testAtLeastOnceErrsWhenZeroInvocations(callable $getClass)
    {
        $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->at_least_once()->return_value(15);
        });

        $this->assertMockObjectsShouldFail("at least once should fail when there are no invocations");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testNoExplicitFrequencyIsImpliedOnce(callable $getClass)
    {
        $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_value(4);
        });

        $this->assertMockObjectsShouldFail("implied once should fail when there are no invocations");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testPassingCallableToWillCausesInvocationWhenMockIsUsed(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->will(function ($i) { return 999 + $i->parameters[0]; });
        });

        $this->assertEquals(1000, $mock::getAnInt(1));
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnValueMapWillRespondWithLastValuesInArrayGivenTheArguments(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply()->return_value_map([
               [10, 20, 30],
               [1, 2, 3],
               [["a" => "b"], ["b" => "c"], 1]
            ])->any();
        });

        $this->assertEquals(30, $mock::multiply(10, 20));
        $this->assertSame(1, $mock::multiply(["a"=> "b"], ["b" => "c"]));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(10, 2);
        }, "Expected no match on the passed arguments");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnThisWillReturnTheClassItself(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_this();
        });

        $this->assertSame($mock, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testThrowExceptionWillTriggerAnExceptionOnUse(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->throw_exception(new \LogicException());
        });

        try {
            $mock::getAnInt();
            $this->fail("There should have been a logic exception thrown");
        } catch (\LogicException $e) {

        }
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testThrowExceptionWillUseADefaultExceptionTypeIfNonePassed(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->throw_exception();
        });

        try {
            $mock::getAnInt();
            $this->fail("There should have been a logic exception thrown");
        } catch (\Exception $e) {
            if (preg_match('/PHPUnit.*/', get_class($e))) {
                throw $e;
            }
        }
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnConsecutivelyReturnsValuesInASequence(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_consecutively([1,2,3]);
        });

        $this->assertEquals(1, $mock::getAnInt());
        $this->assertEquals(2, $mock::getAnInt());
        $this->assertEquals(3, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testReturnShmockOpensNestedMockingFacility(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->getAnInt()->return_shmock('\Shmock\ClassToMockStatically', function ($toMock) {
                // unimportant
            });
        });

        $nestedMock = $mock::getAnInt();
        $this->assertTrue(is_a($nestedMock,'\Shmock\ClassToMockStatically'));
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testUnmockedFunctionsRemainIntact(callable $getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
        });

        $this->assertSame(1, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testUnmockedFunctionsElidedIfPreservationDisabled($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->dont_preserve_original_methods();
        });

        $this->assertNull($mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testOrderMattersWillEnforceCorrectOrderingOfCalls($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->order_matters();
            $staticClass->getAnInt()->return_value(2);
            $staticClass->getAnInt()->return_value(4);
        });

        $this->assertSame(2, $mock::getAnInt());
        $this->assertSame(4, $mock::getAnInt());
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testOrderMattersWillPreventOutOfOrderCalls($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->order_matters();
            $staticClass->getAnInt()->return_value(2);
            $staticClass->multiply(2, 2)->return_value(4);
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2, 2);
        }, "Expected the multiply call to be out of order");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testArgumentsShouldBeEnforced($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply(2,2)->return_value(4);
        });

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2,3);
        }, "Expected the multiply call to err due to bad args");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testArrayArgumentsShouldBeEnforced($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply([2,2],2)->any()->return_value([4,4]);
        });

        $this->assertSame([4,4], $mock::multiply([2,2],2));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply([2,3],3);
        }, "Expected the multiply call to err due to bad args");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testPHPUnitConstraintsAllowed($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply($this->isType("integer"), $this->greaterThan(2))->any()->return_value(10);
        });

        // just bare with me here...
        $this->assertSame(10, $mock::multiply(2, 4));
        $this->assertSame(10, $mock::multiply(10, 5));

        $this->assertFailsMockExpectations(function () use ($mock) {
            $mock::multiply(2.0, 1);
        }, "expected the underlying constraints to fail");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testShmockCanSubclassFunctionsWithReferenceArgs($getClass)
    {
        $a = 5;
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->reference(5)->will(function ($inv) {
                return $inv->parameters[0] + 1;
            });
        });

        $a = $mock::reference($a);
        $this->assertSame(6, $a, "expected shmock to preserve reference semantics");
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testJuggledTypesAreConsideredMatches($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply("1", "2")->return_value(2);
        });

        $this->assertSame(2, $mock::multiply(1, 2.0));
    }

    /**
     * @dataProvider instanceProviders
     */
    public function testExtraArgumentsWithNoExplicitConstraintAreIgnored($getClass)
    {
        $mock = $this->buildMockClass($getClass, function ($staticClass) {
            $staticClass->multiply(1,2)->return_value(2);
        });

        $this->assertSame(2, $mock::multiply(1,2,3));
    }
}

class ClassToMockStatically
{
    private $a;

    public function __construct($a = null)
    {
        $this->a = $a;
    }

    public function getA()
    {
        return $this->a;
    }

    public static function getAnInt()
    {
        return 1;
    }

    public static function multiply($a, $b)
    {
        return $a * $b;
    }

    public static function reference(& $a)
    {
        $a++;
    }
}
