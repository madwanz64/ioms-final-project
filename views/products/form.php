<?php
/**
 * Form tambah/edit produk. Atribut HTML5 (required, min, pattern) hanya
 * membantu user — semua aturan divalidasi ulang di ProductService (VAL-01).
 *
 * @var App\Core\View $view
 * @var App\Entity\User $currentUser
 * @var string $csrfToken
 * @var int $uploadMaxBytes
 * @var App\Entity\Product|null $product null = mode tambah
 * @var array<string, string> $old
 * @var array<string, string> $errors
 * @var list<App\Entity\Category> $categories
 */
$isEdit = $product !== null;
$value = static fn (string $key): string => $old[$key] ?? '';
$fieldClass = static fn (string $key): string => isset($errors[$key]) ? 'field has-error' : 'field';
$errorFor = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? '<span class="error-msg" id="err-' . e($key) . '">' . e($errors[$key]) . '</span>' : '';
};
$describedBy = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
$action = $isEdit ? '/products/' . rawurlencode($product->sku) : '/products';
$maxMb = rtrim(rtrim(number_format($uploadMaxBytes / 1048576, 1), '0'), '.');
?>
<?= $view->partial('partials/topbar', [
    'currentUser' => $currentUser,
    'title' => $isEdit ? 'Edit Produk' : 'Tambah Produk',
    'subtitle' => $isEdit ? $product->sku . ' — SKU tidak dapat diubah' : 'SKU unik, angka harus bilangan bulat >= 0',
]) ?>

<?php if ($errors !== []): ?>
  <div class="alert error" role="alert">Data belum disimpan. Periksa <?= e(count($errors)) ?> isian yang ditandai di bawah.</div>
<?php endif; ?>

<form class="data-form wide animate-in" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
  <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

  <div class="form-row">
    <div class="<?= $fieldClass('sku') ?>">
      <label for="sku">SKU <span class="req">*</span></label>
      <?php if ($isEdit): ?>
        <input type="text" id="sku" value="<?= e($product->sku) ?>" readonly>
        <span class="hint">SKU dipakai riwayat order & ledger, jadi tidak dapat diubah.</span>
      <?php else: ?>
        <input type="text" id="sku" name="sku" value="<?= e($value('sku')) ?>" placeholder="SKU-0033" required
               maxlength="20" pattern="[A-Za-z0-9\-]{3,20}" autocapitalize="characters"<?= $describedBy('sku') ?>>
        <span class="hint">3–20 karakter: huruf, angka, tanda minus. Disimpan huruf besar.</span>
      <?php endif; ?>
      <?= $errorFor('sku') ?>
    </div>
    <div class="<?= $fieldClass('name') ?>">
      <label for="name">Nama Produk <span class="req">*</span></label>
      <input type="text" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="150"<?= $describedBy('name') ?>>
      <?= $errorFor('name') ?>
    </div>
  </div>

  <div class="form-row">
    <div class="<?= $fieldClass('category_id') ?>">
      <label for="category_id">Kategori <span class="req">*</span></label>
      <select id="category_id" name="category_id" required<?= $describedBy('category_id') ?>>
        <option value="">— Pilih kategori —</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= e($category->id) ?>"<?= $value('category_id') === (string) $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
        <?php endforeach; ?>
      </select>
      <?= $errorFor('category_id') ?>
    </div>
    <div class="<?= $fieldClass('unit') ?>">
      <label for="unit">Unit <span class="req">*</span></label>
      <input type="text" id="unit" name="unit" value="<?= e($value('unit')) ?>" placeholder="pcs / box / rim" required maxlength="20"<?= $describedBy('unit') ?>>
      <?= $errorFor('unit') ?>
    </div>
  </div>

  <div class="form-row">
    <div class="<?= $fieldClass('buy_price') ?>">
      <label for="buy_price">Harga Beli (Rp) <span class="req">*</span></label>
      <input type="number" id="buy_price" name="buy_price" value="<?= e($value('buy_price')) ?>" min="0" step="1" required inputmode="numeric"<?= $describedBy('buy_price') ?>>
      <?= $errorFor('buy_price') ?>
    </div>
    <div class="<?= $fieldClass('sell_price') ?>">
      <label for="sell_price">Harga Jual (Rp) <span class="req">*</span></label>
      <input type="number" id="sell_price" name="sell_price" value="<?= e($value('sell_price')) ?>" min="0" step="1" required inputmode="numeric"<?= $describedBy('sell_price') ?>>
      <?= $errorFor('sell_price') ?>
    </div>
  </div>

  <div class="form-row">
    <div class="<?= $fieldClass('reorder_point') ?>">
      <label for="reorder_point">Reorder Point <span class="req">*</span></label>
      <input type="number" id="reorder_point" name="reorder_point" value="<?= e($value('reorder_point')) ?>" min="0" step="1" required inputmode="numeric"<?= $describedBy('reorder_point') ?>>
      <span class="hint">Produk ditandai low stock bila total stok di bawah angka ini.</span>
      <?= $errorFor('reorder_point') ?>
    </div>
    <div class="<?= $fieldClass('active') ?>">
      <label for="active">Status</label>
      <select id="active" name="active"<?= $describedBy('active') ?>>
        <option value="1"<?= $value('active') !== '0' ? ' selected' : '' ?>>Aktif</option>
        <option value="0"<?= $value('active') === '0' ? ' selected' : '' ?>>Nonaktif</option>
      </select>
      <span class="hint">Produk tidak dapat dihapus, hanya dinonaktifkan.</span>
      <?= $errorFor('active') ?>
    </div>
  </div>

  <div class="<?= $fieldClass('image') ?>">
    <label for="image">Gambar Produk (opsional)</label>
    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
      <div class="img-drop" style="cursor:default;" data-image-preview>
        <?php if ($isEdit && $product->imageUrl !== null): ?>
          <img src="<?= e($product->imageUrl) ?>" alt="Gambar saat ini">
        <?php else: ?>
          <span>Belum ada<br>gambar</span>
        <?php endif; ?>
      </div>
      <div>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png" data-max-bytes="<?= e($uploadMaxBytes) ?>"<?= $describedBy('image') ?>>
        <div class="hint">JPG/PNG, maksimal <?= e($maxMb) ?> MB. Tipe file diperiksa ulang di server dari isi file.<?= $isEdit ? ' Kosongkan bila tidak ingin mengganti gambar.' : '' ?></div>
        <?= $errorFor('image') ?>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn primary">Simpan Produk</button>
    <a class="btn ghost" href="<?= e($isEdit ? '/products/' . rawurlencode($product->sku) : '/products') ?>">Batal</a>
  </div>
</form>
