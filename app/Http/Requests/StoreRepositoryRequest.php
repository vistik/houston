<?php

namespace App\Http\Requests;

use App\GitHub\GitHubClient;
use App\Models\Repository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Validator;
use LogicException;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * Accepts `owner/name` as well as pasted GitHub URLs.
     */
    private const string Pattern = '~^(?:https?://)?(?:www\.)?(?:github\.com/)?(?<owner>[A-Za-z0-9_.-]+)/(?<name>[A-Za-z0-9_.-]+?)(?:\.git)?/?(?:[/?#].*)?$~';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'max:200', 'regex:'.self::Pattern],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'repository.regex' => 'Use the owner/name format, e.g. laravel/framework.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                ['owner' => $owner, 'name' => $name] = $this->coordinates();

                if (Repository::query()->where('owner', $owner)->where('name', $name)->exists()) {
                    $validator->errors()->add('repository', "{$owner}/{$name} is already being watched.");

                    return;
                }

                try {
                    app(GitHubClient::class)->get("repos/{$owner}/{$name}");
                } catch (RequestException) {
                    $validator->errors()->add('repository', "Could not find {$owner}/{$name} on GitHub, or the token has no access to it.");
                }
            },
        ];
    }

    /**
     * @return array{owner: string, name: string}
     */
    public function coordinates(): array
    {
        if (! preg_match(self::Pattern, trim($this->string('repository')->toString()), $matches)) {
            throw new LogicException('Coordinates are only available after the repository passed validation.');
        }

        return ['owner' => $matches['owner'], 'name' => $matches['name']];
    }
}
