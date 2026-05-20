# Plan - Client form (fix Twig 500 + add N° d’agrément)

## Information Gathered
- `templates/client/new.html.twig` references `form.code` at line ~26, but 
- `src/Form/ClientType.php` currently defines only: `companyName`, `address`, `phone`, `email`, `isActive` (no `code`). This causes Twig error: `form.code` doesn’t exist.
- `src/Entity/Client.php` already has property `code` with `getCode()/setCode()` and generates it in `__construct()`.
- `templates/client/edit.html.twig` also references `form.code` (and `form.contactName`, which likely doesn’t exist either, but current reported error is for `new.html.twig`).
- User wants: add a field in the creation form named **`N° d’agrément`**.

## Plan
### Step 1 — Fix runtime error (Twig 500)
- Update `src/Form/ClientType.php` to include field `code`.
  - Make it `disabled` (or `readonly`), and `mapped` appropriately so it won’t require user input.
  - Recommended: `->add('code', TextType::class, ['disabled' => true, 'label' => 'Code client', ...])`

### Step 2 — Add “N° d’agrément” field
- Update `src/Entity/Client.php`:
  - Add property `agrémentNumber` (name in PHP can be `numeroAgrement`), with Doctrine column (string) + getter/setter.
  - Add validation constraints (e.g. `NotBlank`, `Length`).
- Update `src/Form/ClientType.php`:
  - Add corresponding form field with label `N° d’agrément`.
- Update `templates/client/new.html.twig`:
  - Add the new form field rendering between existing fields.
- (Optional but recommended) Update `templates/client/edit.html.twig` to display the same new field and fix any other missing fields.

### Step 3 — Database schema
- Create and run a Doctrine migration after entity change.

### Step 4 — Test
- Clear cache and open `nouveau client` page.
- Submit the form and verify fields are persisted.

## Dependent Files to be edited
- `src/Form/ClientType.php`
- `src/Entity/Client.php`
- `templates/client/new.html.twig`
- (Optional) `templates/client/edit.html.twig`

## Followup steps
- Run:
  - `php bin/console make:migration`
  - `php bin/console doctrine:migrations:migrate`
  - `php bin/console cache:clear`
  - Then test the “Nouveau client” page.

<ask_followup_question>
Souhaites-tu que le champ “N° d’agrément” soit **obligatoire** (validation NotBlank) ou **optionnel** ?
</ask_followup_question>

