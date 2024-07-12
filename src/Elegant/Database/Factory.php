<?php

namespace Elegant\Database;

use Elegant\Database\Model\Factories\Sequence;
use Elegant\Support\Collection;
use Elegant\Support\Str;
use Elegant\Support\Traits\Macroable;

abstract class Factory
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model;

    /**
     * The number of models that should be generated.
     *
     * @var int|null
     */
    protected $count;

    /**
     * The state transformations that will be applied to the model.
     *
     * @var \Elegant\Support\Collection
     */
    protected $states;

    /**
     * The current Faker instance.
     *
     * @var \Faker\Generator
     */
    protected $faker;

    /**
     * The default namespace where factories reside.
     *
     * @var string
     */
    public static $namespace = 'Database\\Factories\\';

    /**
     * The factory name resolver.
     *
     * @var callable
     */
    protected static $factoryNameResolver;
    /**
     * Create a new factory instance.
     *
     * @param int|null $count
     * @param Collection|null $states
     */
    public function __construct(int $count = null,
                                ?Collection $states = null)
    {
        $this->count = $count;
        $this->states = $states ?: new Collection;
        $this->faker = $this->withFaker();
    }

    /**
     * Create a collection of models and persist them to the database.
     *
     * @param array $attributes
     * @param array|null $parent
     * @return mixed
     */
    public function create(array $attributes = [], array $parent = null)
    {
        if (!empty($attributes)) {
            return $this->state($attributes)->create([], $parent);
        }

        $results = $this->make($attributes, $parent);

        if(is_array($results)) {
            $results = $this->store(
                isset($results[0]) ? collect($results) : collect([$results])
            );
        }

        return $results;
    }

    /**
     * Set the connection name on the results and store them.
     *
     * @param  \Elegant\Support\Collection  $results
     * @return mixed
     */
    protected function store(Collection $results)
    {
        $data = collect();

        $results->each(function ($model) use ($data) {
            $data->push($this->newModel($this->getExpandedAttributes($model)));
        });

        return $data;
    }

    /**
     * Define the model's default state.
     *
     * @return array
     */
    abstract public function definition(): array;

    /**
     * Get a new factory instance for the given attributes.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return static
     */
    public static function new($attributes = [])
    {
        return (new static)->state($attributes)->configure();
    }

    /**
     * Configure the factory.
     *
     * @return static
     */
    public function configure()
    {
        return $this;
    }

    /**
     * Create a collection of models.
     *
     * @param array $attributes
     * @param array|null $parent
     * @return mixed
     */
    public function make(array $attributes = [], array $parent = null)
    {
        if (!empty($attributes)) {
            return $this->state($attributes)->make([], $parent);
        }

        if ($this->count === null) {
            return $this->makeInstance($parent);
        }

        if ($this->count < 1) {
            return $this->newModel();
        }

        return array_map(function () use ($parent) {
            return $this->makeInstance($parent);
        }, range(1, $this->count));
    }

    /**
     * Make an instance of the model with the given attributes.
     *
     * @param array|null $parent
     * @return mixed
     */
    protected function makeInstance(?array $parent)
    {
        return $this->getExpandedAttributes($parent);
    }

    /**
     * Get a raw attributes array for the model.
     *
     * @param  array|null  $parent
     * @return array
     */
    protected function getExpandedAttributes(?array $parent): array
    {
        return $this->expandAttributes($this->getRawAttributes($parent));
    }

    /**
     * Get the raw attributes for the model as an array.
     *
     * @param array|null $parent
     * @return mixed
     */
    protected function getRawAttributes(?array $parent)
    {
        return $this->states->pipe(function ($states) {
            return collect($states);
        })->reduce(function ($carry, $state) use ($parent) {
            if ($state instanceof \Closure) {
                $state = $state->bindTo($this);
            }

            return array_merge($carry, $state($carry, $parent));
        }, $this->definition());
    }

    /**
     * Expand all attributes to their underlying values.
     *
     * @param  array  $definition
     * @return array
     */
    protected function expandAttributes(array $definition): array
    {
        return collect($definition)->map(function ($attribute, $key) use (&$definition) {
            if (is_callable($attribute) && ! is_string($attribute) && ! is_array($attribute)) {
                $attribute = $attribute($definition);
            }

            if ($attribute instanceof self) {
                show_error('Attribute is instance of self. Implement this behavior!');
//                $attribute = $attribute->create()->getKey();
            }

            $definition[$key] = $attribute;

            return $attribute;
        })->all();
    }


    /**
     * Add a new state transformation to the model definition.
     *
     * @param callable|array $state
     * @return static
     */
    public function state($state)
    {
        return $this->newInstance([
            'states' => $this->states->concat([
                is_callable($state) ? $state : function () use ($state) {
                    return $state;
                },
            ]),
        ]);
    }

    /**
     * Add a new sequenced state transformation to the model definition.
     *
     * @param  mixed  ...$sequence
     * @return static
     */
    public function sequence(...$sequence)
    {
        return $this->state(new Sequence(...$sequence));
    }

    /**
     * Specify how many models should be generated.
     *
     * @param int|null $count
     * @return static
     */
    public function count(?int $count)
    {
        return $this->newInstance(['count' => $count]);
    }

    /**
     * Create a new instance of the factory builder with the given mutated properties.
     *
     * @param array $arguments
     * @return static
     */
    protected function newInstance(array $arguments = [])
    {
        return new static(...array_values(array_merge([
            'count' => $this->count,
            'states' => $this->states,
        ], $arguments)));
    }

    /**
     * Get a new model instance.
     *
     * @param array $attributes
     * @return mixed
     */
    public function newModel(array $attributes = [])
    {
        $model = $this->modelName();

        return (new $model)->create($attributes)->first();
    }

    /**
     * Get the name of the model that is generated by the factory.
     *
     * @return string
     */
    public function modelName()
    {
        return $this->model;
    }

    /**
     * Get a new factory instance for the given model name.
     *
     * @param class-string<\App\Core\MY_Model> $modelName
     * @return \Elegant\Database\Factory>
     */
    public static function factoryForModel(string $modelName)
    {
        $factory = static::resolveFactoryName($modelName);

        return $factory::new();
    }

    /**
     * Get a new Faker instance.
     *
     * @return \Faker\Generator
     */
    protected function withFaker()
    {
        return app('faker', \Faker\Factory::create(config_item('faker_locale')));
    }

    /**
     * Get the factory name for the given model name.
     *
     * @param  class-string<\App\Core\MY_Model>  $modelName
     * @return class-string<\Elegant\Database\Factory>
     */
    public static function resolveFactoryName(string $modelName)
    {
        $resolver = static::$factoryNameResolver ?? function (string $modelName) {
            $appNamespace = 'App\\';

            $modelName = Str::startsWith($modelName, $appNamespace.'Models\\')
                ? Str::after($modelName, $appNamespace.'Models\\')
                : Str::after($modelName, $appNamespace);

            return static::$namespace.$modelName.'Factory';
        };

        return $resolver($modelName);
    }
}
