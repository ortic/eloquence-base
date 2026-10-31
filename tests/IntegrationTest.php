<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Contracts\CleansAttributes;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Relations\JoinerFactory;
use Sofa\Eloquence\Searchable\ParserFactory;

/**
 * Eloquence on a real database (SQLite in memory) instead of mocks: what broke with newer Laravel versions shows up
 * only when a model boots and talks to the query builder.
 */
class IntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule;
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setEventDispatcher(new Dispatcher(new Container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // every test has a new dispatcher: boot the model again so its listeners land on it
        Model::clearBootedModels();

        // what BaseServiceProvider does in an application
        Builder::setJoinerFactory(new JoinerFactory);
        Builder::setParserFactory(new ParserFactory);

        Capsule::schema()->create('integration_users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
        });
    }

    /** @test */
    public function a_model_boots_and_an_insert_returns_its_id()
    {
        // Laravel 13 refuses observe() while a model boots; Laravel 10+ looks passthru methods up lower-cased,
        // so insertGetId() returned the builder, which then became the model's key
        $first = IntegrationUser::create(['name' => 'Anna']);
        $second = IntegrationUser::create(['name' => 'Ben']);

        $this->assertSame(1, (int) $first->getKey());
        $this->assertSame(2, (int) $second->getKey());
        $this->assertSame(2, IntegrationUser::count());
    }

    /** @test */
    public function the_cleaner_drops_attributes_that_are_no_columns_before_saving()
    {
        $user = new IntegrationUser(['name' => 'Anna']);
        $user->not_a_column = 'ignored';

        $this->assertTrue($user->save());
        $this->assertArrayNotHasKey('not_a_column', $user->getAttributes());
        $this->assertSame('Anna', IntegrationUser::first()->name);
    }

    /** @test */
    public function query_builder_methods_pass_through()
    {
        IntegrationUser::create(['name' => 'Anna']);

        $this->assertIsString(IntegrationUser::where('name', 'Anna')->toSql());
        $this->assertSame(['Anna'], IntegrationUser::where('name', 'Anna')->getBindings());
        $this->assertTrue(IntegrationUser::where('name', 'Nobody')->doesntExist());
        $this->assertTrue(IntegrationUser::where('name', 'Anna')->exists());
        $this->assertInstanceOf(Connection::class, IntegrationUser::query()->getConnection());
        $this->assertSame(1, IntegrationUser::where('name', 'Anna')->count());
        $this->assertSame(['Anna'], IntegrationUser::pluck('name')->all());
    }

    /** @test */
    public function search_finds_rows_by_relevance()
    {
        IntegrationUser::create(['name' => 'Anna Muster', 'email' => 'anna@example.com']);
        IntegrationUser::create(['name' => 'Ben Beispiel', 'email' => 'ben@example.com']);

        $found = IntegrationUser::search('anna', ['name' => 10, 'email' => 5])->get();

        $this->assertSame(['Anna Muster'], $found->pluck('name')->all());
    }
}

class IntegrationUser extends Model implements CleansAttributes
{
    use Eloquence;

    protected $table = 'integration_users';

    protected $guarded = [];

    public $timestamps = false;
}
