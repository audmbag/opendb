<?php

namespace App\Console\Commands\Concerns;

use App\Console\Commands\Settings\ConsoleFormRequest;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\select;

/**
 * The commands managing the users, the roles and the privileges: an administrator logs in, and the answers are
 * checked by the requests of the matching pages, so that the console allows exactly what the interface allows.
 */
trait ManagesAccounts
{
    use AuthenticatesUser;

    /**
     * The account running the command, once its credentials are checked, or null once the failure is reported.
     */
    protected function logIn(): ?User
    {
        $user = $this->authenticateUser();

        if ($user !== null) {
            Auth::setUser($user);
        }

        return $user;
    }

    /**
     * The request validated with the answers, or null once its refusal is reported.
     *
     * @template TRequest of FormRequest
     *
     * @param  class-string<TRequest>  $requestClass
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $routeParameters
     * @return TRequest|null
     */
    protected function validated(string $requestClass, array $answers, array $routeParameters = []): ?FormRequest
    {
        return $this->reporting(fn (): FormRequest => ConsoleFormRequest::validate($requestClass, $answers, $routeParameters));
    }

    /**
     * The result of the callback, or null once the refusal of the validation or of the privileges is reported.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult|null
     */
    protected function reporting(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->error($message);
            }
        } catch (AuthorizationException) {
            $this->error(__('users.errors.denied'));
        }

        return null;
    }

    /**
     * The account given by its name, or else chosen among every account. Null once the reason is reported.
     */
    protected function chooseAccount(?string $given, string $label): ?User
    {
        $name = $given ?? select($label, User::query()->orderBy('name')->pluck('name', 'name')->all(), scroll: 10);
        $account = User::query()->firstWhere('name', $name);

        if ($account === null) {
            $this->error(__('users.errors.unknown_user', ['name' => $name]));
        }

        return $account;
    }

    /**
     * The role given by its name, or else chosen among every role. Null once the reason is reported.
     */
    protected function chooseRole(?string $given): ?Role
    {
        $roles = Role::query()->orderBy('name')->pluck('name', 'name')->all();

        if ($given === null && $roles === []) {
            $this->error(__('users.errors.no_role'));

            return null;
        }

        $name = $given ?? select(__('users.fields.role'), $roles, scroll: 10);
        $role = Role::query()->firstWhere('name', $name);

        if ($role === null) {
            $this->error(__('users.errors.unknown_role', ['name' => $name]));
        }

        return $role;
    }

    /**
     * The ids of the roles given by their names, or null once an unknown name is reported.
     *
     * @param  list<string>  $names
     * @return list<int>|null
     */
    protected function roleIds(array $names): ?array
    {
        $ids = Role::query()->whereIn('name', $names)->pluck('id', 'name');
        $unknown = array_find($names, fn (string $name): bool => ! $ids->has($name));

        if ($unknown !== null) {
            $this->error(__('users.errors.unknown_role', ['name' => $unknown]));

            return null;
        }

        return array_values($ids->only($names)->all());
    }
}
