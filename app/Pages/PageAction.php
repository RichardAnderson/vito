<?php

namespace App\Pages;

use App\DTOs\DynamicForm;
use App\Models\User;
use App\Pages\Components\Forms\Field;
use App\Pages\Schema\EvaluatesClosures;
use App\Pages\Schema\EvaluationContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A page mutation: POST {page}/actions/{id}. Carries an optional DynamicForm
 * (server-side validation → 422), input-borne model binds (IDOR-safe), a write-level
 * authorize closure (mandatory unless the page supplies a defaultAuthorize), and a
 * `run()` callback delegating to an existing app/Actions class. The callback,
 * authorize check and form are all closure-DI evaluated — `fn (Site $site, array $input)`.
 */
final class PageAction
{
    use EvaluatesClosures;

    private string $method = 'post';

    private ?DynamicForm $form = null;

    /**
     * @var array<int, Binding>
     */
    private array $binds = [];

    private ?Closure $authorize = null;

    private ?Closure $callback = null;

    private string|Closure|null $modalHeading = null;

    private string|Closure|null $modalDescription = null;

    /**
     * @var array<int, Field>
     */
    private array $formFields = [];

    private bool $sheet = false;

    private string|Closure|null $confirm = null;

    private string|Closure|null $confirmText = null;

    private string $confirmField = 'confirmation';

    private bool $destructive = false;

    private ?string $success = null;

    private ?string $routeName = null;

    public function __construct(
        private readonly string $id,
    ) {}

    public static function make(string $id): self
    {
        return new self($id);
    }

    public function method(string $method): self
    {
        $this->method = strtolower($method);

        return $this;
    }

    public function post(): self
    {
        return $this->method('post');
    }

    public function patch(): self
    {
        return $this->method('patch');
    }

    public function put(): self
    {
        return $this->method('put');
    }

    public function delete(): self
    {
        return $this->method('delete');
    }

    public function bind(string $param, string $model, ?string $scopedTo = null, ?string $foreignKey = null): self
    {
        $this->binds[] = $scopedTo === null
            ? Binding::root($param, $model)
            : Binding::make($param, $model, $scopedTo, $foreignKey);

        return $this;
    }

    /**
     * Write-level gate, closure-DI evaluated: `fn (User $user, Site $site): bool`.
     */
    public function authorize(Closure $check): self
    {
        $this->authorize = $check;

        return $this;
    }

    /**
     * The behaviour, closure-DI evaluated: `fn (Site $site, array $input) => …`.
     */
    public function run(Closure $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Legacy alias for run() — the closure is DI-evaluated the same way, so existing
     * `fn (array $models, array $input, Request $request)` handlers keep working.
     */
    public function handler(Closure $callback): self
    {
        return $this->run($callback);
    }

    /**
     * Flash a success message when the callback returns no redirect of its own.
     */
    public function success(string $message): self
    {
        $this->success = $message;

        return $this;
    }

    /**
     * Heading for the action's modal/dialog (the row's edit dialog title).
     */
    public function modalHeading(string|Closure $heading): self
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function modalDescription(string|Closure $description): self
    {
        $this->modalDescription = $description;

        return $this;
    }

    /**
     * The modal's form fields (Filament-style). Rendered into the dialog body; NOT
     * exposed via getForm() so the action map's `form` stays null (validation lives
     * in the delegated app/Actions class).
     *
     * @param  array<int, Field>  $fields
     */
    public function form(array $fields): self
    {
        $this->formFields = $fields;

        return $this;
    }

    /**
     * Render the modal as a side sheet rather than a centered dialog.
     */
    public function sheet(bool $sheet = true): self
    {
        $this->sheet = $sheet;

        return $this;
    }

    public function confirm(string|Closure $message): self
    {
        $this->confirm = $message;

        return $this;
    }

    public function confirmText(string|Closure $value, string $field = 'confirmation'): self
    {
        $this->confirmText = $value;
        $this->confirmField = $field;

        return $this;
    }

    /**
     * Style the trigger button as destructive (e.g. delete).
     */
    public function destructive(bool $destructive = true): self
    {
        $this->destructive = $destructive;

        return $this;
    }

    /**
     * Override the generated route name. Normally unnecessary — the framework derives
     * `{page-route}.{id}`, which already reproduces the legacy `site-settings.*` names.
     */
    public function routeName(string $name): self
    {
        $this->routeName = $name;

        return $this;
    }

    public function getRouteName(?string $default = null): ?string
    {
        return $this->routeName ?? $default;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getForm(): ?DynamicForm
    {
        return $this->form;
    }

    /**
     * @return array<int, Binding>
     */
    public function getBinds(): array
    {
        return $this->binds;
    }

    public function getModalHeading(): string|Closure|null
    {
        return $this->modalHeading;
    }

    public function getModalDescription(): string|Closure|null
    {
        return $this->modalDescription;
    }

    /**
     * @return array<int, Field>
     */
    public function getFormFields(): array
    {
        return $this->formFields;
    }

    public function isSheet(): bool
    {
        return $this->sheet;
    }

    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    public function getConfirm(): string|Closure|null
    {
        return $this->confirm;
    }

    public function getConfirmText(): string|Closure|null
    {
        return $this->confirmText;
    }

    public function getConfirmField(): string
    {
        return $this->confirmField;
    }

    public function hasAuthorization(): bool
    {
        return $this->authorize !== null;
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     */
    public function isAuthorized(User $user, array $models): bool
    {
        if ($this->authorize === null) {
            return false;
        }

        return (bool) $this->evaluate($this->authorize, EvaluationContext::make($models, $user));
    }

    /**
     * @param  array<string, \Illuminate\Database\Eloquent\Model>  $models
     * @param  array<string, mixed>  $input
     */
    public function dispatch(array $models, array $input, Request $request): mixed
    {
        if ($this->callback === null) {
            throw new RuntimeException("Page action '{$this->id}' has no handler.");
        }

        $ctx = new EvaluationContext($models, $request->user(), $request, null, $input);
        $result = $this->evaluate($this->callback, $ctx);

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        if ($this->success !== null) {
            return back()->with('success', $this->success);
        }

        return $result;
    }
}
