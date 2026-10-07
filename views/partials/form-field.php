<?php
/**
 * Satu field form: label + input/textarea/select + hint + pesan error (VAL-01, UI-01).
 *
 * @var string $name
 * @var string $label
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var string|null $type text|email|password|textarea|select (default text)
 * @var array<string, string>|null $options untuk select: value => label
 * @var bool|null $required
 * @var int|null $maxlength
 * @var string|null $hint
 * @var string|null $autocomplete
 */
$type ??= 'text';
$required ??= false;
$error = $errors[$name] ?? null;
$id = 'f-' . $name;
$value = $type === 'password' ? '' : ($old[$name] ?? '');
$attrs = ' name="' . e($name) . '"'
    . ($required ? ' required' : '')
    . (isset($maxlength) ? ' maxlength="' . e($maxlength) . '"' : '')
    . (isset($autocomplete) ? ' autocomplete="' . e($autocomplete) . '"' : '')
    . ($error !== null ? ' aria-invalid="true" aria-describedby="err-' . e($name) . '"' : '');
?>
<div class="field<?= $error !== null ? ' has-error' : '' ?>">
  <label for="<?= e($id) ?>"><?= e($label) ?><?= $required ? ' <span class="req">*</span>' : '' ?></label>
  <?php if ($type === 'textarea'): ?>
    <textarea id="<?= e($id) ?>"<?= $attrs ?>><?= e($value) ?></textarea>
  <?php elseif ($type === 'select'): ?>
    <select id="<?= e($id) ?>"<?= $attrs ?>>
      <?php foreach ($options ?? [] as $optionValue => $optionLabel): ?>
        <option value="<?= e($optionValue) ?>"<?= (string) $optionValue === $value ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <input type="<?= e($type) ?>" id="<?= e($id) ?>" value="<?= e($value) ?>"<?= $attrs ?>>
  <?php endif; ?>
  <?php if (($hint ?? '') !== ''): ?>
    <span class="hint"><?= e($hint) ?></span>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <span class="error-msg" id="err-<?= e($name) ?>"><?= e($error) ?></span>
  <?php endif; ?>
</div>
