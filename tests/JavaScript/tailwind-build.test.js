import assert from 'node:assert/strict';
import test from 'node:test';
import postcss from 'postcss';
import { build } from 'vite';

test('the production build preserves PHP tone classes, manual dark mode, forms and native progress styling', async () => {
    const bundle = await build({ logLevel: 'silent', build: { write: false } });
    const stylesheet = bundle.output.find((asset) => asset.type === 'asset' && asset.fileName.endsWith('.css'));

    assert.ok(stylesheet, 'Vite must generate an application stylesheet');
    const css = postcss.parse(stylesheet.source);
    const rules = [];
    const declarations = new Map();
    css.walkRules((rule) => rules.push(rule));
    css.walkDecls((declaration) => declarations.set(declaration.prop, declaration.value));

    for (const hue of ['emerald', 'sky', 'amber', 'rose']) {
        assert.ok(rules.some((rule) => rule.selector === `.text-${hue}-700`), `${hue} classes from PHP enums must be included`);
        const dark = rules.find((rule) => rule.selector.includes(`.dark\\:text-${hue}-400`));
        assert.ok(dark?.selector.includes(':where(.dark'), 'dark utilities must respond to the theme selector');
        assert.ok(rules.some((rule) => rule.selector.includes(`.dark\\:bg-${hue}-400\\/15`)), 'tinted dark badges must compile');
    }

    assert.match(declarations.get('--font-sans'), /Figtree/);
    assert.ok(rules.some((rule) => /\[type=["']?text["']?\]/.test(rule.selector)), 'the forms plugin must style text inputs');
    const focus = rules.find((rule) => rule.selector.includes('.focus\\:outline-hidden') && rule.parent.params?.includes('forced-colors'));
    assert.ok(focus?.nodes.some((node) => node.prop === 'outline' && node.value.includes('2px')), 'keyboard focus must retain its forced-colors outline');
    for (const pseudo of ['::-webkit-progress-value', '::-moz-progress-bar']) {
        const fill = rules.find((rule) => rule.selector.includes(`.cc-weight-progress${pseudo}`));
        assert.ok(fill?.nodes.some((node) => node.prop === 'background-color' && node.value === 'var(--cc-weight-progress-color)'));
    }
    const darkTrack = rules.find((rule) => rule.selector === '.dark .cc-weight-progress::-webkit-progress-bar');
    assert.ok(darkTrack?.nodes.some((node) => node.prop === 'background-color' && node.value === 'var(--color-slate-800)'), 'dark mode must precede the native progress pseudo-element');

    const containers = [];
    css.walkAtRules('container', (rule) => containers.push(rule));
    assert.ok(containers.some((rule) => rule.params.includes('weight-field') && rule.toString().includes('cc-weight-percentage-above-bar')));
    assert.equal(declarations.get('--color-sky-400'), '#38bdf8');
    assert.equal(declarations.get('--color-slate-900'), '#0f172a');
    assert.ok(!stylesheet.source.includes('@tailwind '), 'legacy directives must not survive compilation');
});
