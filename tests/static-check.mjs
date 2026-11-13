import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname, '..');
const files = [
  path.join(root, 'sidrena-cijena.php'),
  path.join(root, 'includes', 'cjenik.php'),
  path.join(root, 'src', 'Config.php'),
  path.join(root, 'src', 'Catalog', 'State.php'),
  path.join(root, 'src', 'Catalog', 'StreamWriters.php'),
  path.join(root, 'src', 'Catalog', 'CatalogEngine.php'),
];

const failures = [];
const assert = (condition, message) => {
  if (!condition) failures.push(message);
};

function checkBalanced(source, filename) {
  const pairs = { ')': '(', ']': '[', '}': '{' };
  const openings = new Set(Object.values(pairs));
  const stack = [];
  let state = 'html';
  let escaped = false;

  for (let i = 0; i < source.length; i += 1) {
    const char = source[i];
    const next = source[i + 1];

    if (state === 'html') {
      if (source.startsWith('<?php', i)) {
        state = 'code';
        i += 4;
      }
      continue;
    }

    if (state === 'line-comment') {
      if (char === '?' && next === '>') {
        state = 'html';
        i += 1;
      } else if (char === '\n') {
        state = 'code';
      }
      continue;
    }
    if (state === 'block-comment') {
      if (char === '*' && next === '/') {
        state = 'code';
        i += 1;
      }
      continue;
    }
    if (state === 'single' || state === 'double') {
      if (escaped) {
        escaped = false;
      } else if (char === '\\') {
        escaped = true;
      } else if ((state === 'single' && char === "'") || (state === 'double' && char === '"')) {
        state = 'code';
      }
      continue;
    }

    if (char === '?' && next === '>') {
      state = 'html';
      i += 1;
    } else if (char === '/' && next === '/') {
      state = 'line-comment';
      i += 1;
    } else if (char === '#') {
      state = 'line-comment';
    } else if (char === '/' && next === '*') {
      state = 'block-comment';
      i += 1;
    } else if (char === "'") {
      state = 'single';
    } else if (char === '"') {
      state = 'double';
    } else if (openings.has(char)) {
      stack.push(char);
    } else if (pairs[char]) {
      const opening = stack.pop();
      assert(opening === pairs[char], `${filename}: unbalanced ${char} at offset ${i}`);
    }
  }

  assert(state === 'code' || state === 'html' || state === 'line-comment', `${filename}: unterminated string or block comment`);
  assert(stack.length === 0, `${filename}: ${stack.length} unclosed delimiter(s)`);
}

const runtimeSources = files.map((file) => {
  assert(fs.existsSync(file), `Missing file: ${file}`);
  const source = fs.readFileSync(file, 'utf8');
  checkBalanced(source, path.basename(file));
  return source;
});

const main = runtimeSources[0];
const cjenik = runtimeSources[1];
const catalogRuntime = runtimeSources.slice(2).join('\n');
const allRuntime = runtimeSources.join('\n');
const combined = [main, cjenik].join('\n');
const readme = fs.readFileSync(path.join(root, 'readme.txt'), 'utf8');

assert(main.indexOf("'src/Config.php'") < main.indexOf("'includes/cjenik.php'"), 'Config must be loaded before cjenik.php');
assert(main.includes('Config::registerLegacyConstants()'), 'Legacy constant compatibility registration is missing');
assert(main.includes('* Version: 1.2.4'), 'Plugin header version is not 1.2.4');
assert(main.includes('* Author: Matija Gračanin'), 'Plugin author is not Matija Gračanin');
assert(main.includes('* Author Email: matijag@gmail.com'), 'Plugin author email is missing');
assert(readme.includes('Stable tag: 1.2.4'), 'Readme stable tag is not 1.2.4');
assert(catalogRuntime.includes('namespace SidrenaCijenaCjenik'), 'Neutral plugin namespace is missing');
assert(!allRuntime.includes('MatijaGracanin\\SidrenaCijena'), 'Legacy personal namespace is still present');
assert(main.includes("'pomoc'           => __('Pomoć'"), 'Help tab navigation is missing');
assert(main.includes('function sidrena_cijena_render_help_tab()'), 'Help tab renderer is missing');
assert(main.includes('[sidrena_cjenik format="oba" arhiva="da"]'), 'Help tab shortcode documentation is incomplete');
assert(main.includes("declare_compatibility('custom_order_tables', __FILE__, true)"), 'HPOS compatibility declaration is missing');
assert(main.includes("declare_compatibility('cart_checkout_blocks', __FILE__, true)"), 'Cart and Checkout Blocks compatibility declaration is missing');
assert(main.includes('sidrena_cijena_initialize_product_anchor'), 'Automatic anchor-price initialization is missing');
assert(main.includes("get_option(Config::AUTO_INITIALIZED_OPTION) === 'yes'"), 'One-time initialization guard is missing');

const functionNames = [...combined.matchAll(/function\s+([a-zA-Z0-9_]+)\s*\(/g)].map((match) => match[1]);
const duplicates = functionNames.filter((name, index) => functionNames.indexOf(name) !== index);
assert(duplicates.length === 0, `Duplicate function definitions: ${[...new Set(duplicates)].join(', ')}`);

const callbackNames = [...combined.matchAll(/add_(?:action|filter|shortcode)\(\s*[^,]+,\s*'([a-zA-Z0-9_]+)'/g)].map((match) => match[1]);
for (const callback of callbackNames) {
  assert(functionNames.includes(callback), `Hook callback is not defined: ${callback}`);
}

const definedConstants = new Set([...combined.matchAll(/define\('((?:SIDRENA_CIJENA|CJENIK)_[A-Z0-9_]+)'/g)].map((match) => match[1]));
const usedConstants = new Set([...combined.matchAll(/\b((?:SIDRENA_CIJENA|CJENIK)_[A-Z0-9_]+)\b/g)].map((match) => match[1]));
for (const constant of usedConstants) {
  assert(definedConstants.has(constant), `Plugin constant is used but not defined: ${constant}`);
}

assert(functionNames.includes('cjenik_activate'), 'Activation callback is missing');
assert(functionNames.includes('cjenik_deactivate'), 'Deactivation callback is missing');

const requiredColumns = [
  'naziv',
  'sifra_proizvoda',
  'brend',
  'jedinica_mjere',
  'cijena_po_jedinici',
  'maloprodajna_cijena',
  'poseban_oblik_prodaje',
  'naziv_posebnog_oblika_prodaje',
  'sidrena_cijena',
  'barkod',
  'dostupnost',
];
for (const column of requiredColumns) {
  assert(cjenik.includes(`'${column}'`), `CSV column missing: ${column}`);
}

assert(cjenik.includes("add_shortcode('sidrena_cjenik'"), 'Public price-list shortcode is missing');
assert(cjenik.includes("add_rewrite_rule('^cjenik-proizvoda\\.csv$'"), 'Stable public CSV route is missing');
assert(cjenik.includes("add_rewrite_rule('^cjenik-proizvoda\\.xml$'"), 'Stable public XML route is missing');
assert(cjenik.includes('CJENIK_MIN_RETENTION_DAYS'), 'Retention guard is missing');
assert(cjenik.includes('cjenik_get_inherited_meta($product, $parent, Config::ANCHOR_META_KEY)'), 'Anchor price export is missing');
assert(catalogRuntime.includes("$format . '_download_name'"), 'Timestamped download filenames are missing');
assert(cjenik.includes('encoding="UTF-8"'), 'Explicit UTF-8 XML encoding is missing');
assert(cjenik.includes("'mime'   => 'application/xml; charset=utf-8'"), 'XML content type is missing');
assert(catalogRuntime.includes('class GenerationLock'), 'Shared generation lock is missing');
assert(catalogRuntime.includes('final class Config'), 'Config class is missing');
assert(catalogRuntime.includes('function refresh()'), 'Generation lock lease renewal is missing');
assert(catalogRuntime.includes("(int) $this->ownedValue['expires_at'] + 1"), 'Generation lock renewal must advance even within the same second');
assert(catalogRuntime.includes('class DirtyState'), 'Versioned dirty state is missing');
assert(catalogRuntime.includes('spreadsheetSafeText'), 'CSV formula neutralization is missing');
assert(catalogRuntime.includes("fputcsv($this->handle, $values, $this->delimiter, '\"', '')"), 'CSV writer must use an explicit empty escape parameter');
assert(catalogRuntime.includes("'limit' => self::PAGE_SIZE"), 'Catalog generation is not paginated');
assert(catalogRuntime.includes('rename($operation'), 'Atomic staged publication is missing');
assert(!cjenik.includes("wp_schedule_event($next->getTimestamp(), 'daily'"), 'Fixed-interval daily scheduling is still present');
assert(cjenik.includes('wp_schedule_single_event($next->getTimestamp(), CJENIK_CRON_HOOK)'), 'DST-safe single daily event is missing');

const publicHandler = cjenik.slice(
  cjenik.indexOf('function cjenik_handle_public_download()'),
  cjenik.indexOf('function cjenik_get_archive_files('),
);
assert(!publicHandler.includes('cjenik_generate('), 'Anonymous public download must not generate the catalog');
assert(fs.existsSync(path.join(root, 'NOTICE.md')), 'GPL provenance notice is missing');

if (failures.length > 0) {
  console.error(failures.map((failure) => `FAIL: ${failure}`).join('\n'));
  process.exit(1);
}

console.log(`OK: static checks passed for ${files.length} PHP files (${functionNames.length} global functions, ${callbackNames.length} hooks).`);
