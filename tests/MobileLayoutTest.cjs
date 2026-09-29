// Isolated layout regression: simulated native CSS, not a deployed Dolibarr test.
const { chromium } = require('playwright');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const assert = require('node:assert/strict');
(async () => {
	const browser = await chromium.launch({ channel: 'msedge', headless: true });
	try {
		const page = await browser.newPage();
		const css = readFileSync(join(__dirname, '../css/timesheetweek_card_mobile.css'), 'utf8');
		for (const width of [320, 360, 393, 480, 600, 768]) {
			await page.setViewportSize({ width, height: 780 });
			await page.setContent(`<style>
			body { margin:8px; font:16px Arial } .centpercent { width:100% }
			.nowraponall { white-space:nowrap } td { padding:8px }
			input { padding:8px; border:1px solid } ${css}
			</style><body class="timesheetweek-mobile"><div class="timesheetweek-mobile-card">
			<table class="centpercent tw-mobile-task-table"><tr><td class="tw-task-label">
			<span class="tw-task-reference"><a class="nowraponall">TK2605-0462</a> - </span>
			<a class="nowraponall">Préparation dossiers Consuel et intervention supplémentaire</a></td>
			<td class="tw-task-entry"><div class="tw-task-day-field"><input value="00:00"></div></td>
			</tr></table></div></body>`);
			const metrics = await page.evaluate(() => {
				const input = document.querySelector('input');
				const style = getComputedStyle(input);
				const canvas = document.createElement('canvas').getContext('2d');
				canvas.font = style.font;
				return {
					available: input.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight),
					text: canvas.measureText('00:00').width,
					overflow: document.documentElement.scrollWidth > innerWidth,
					hidden: getComputedStyle(document.querySelector('.tw-task-reference')).display === 'none'
				};
			});
			assert.ok(metrics.available >= metrics.text + 8, `Input too narrow at ${width}px`);
			assert.equal(metrics.overflow, false, `Overflow at ${width}px`);
			assert.equal(metrics.hidden, width <= 480);
			await page.locator('input').fill('09:30');
			assert.equal(await page.locator('input').inputValue(), '09:30');
		}
		console.log('Mobile layout passed: 320, 360, 393, 480, 600, 768px.');
	} finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
