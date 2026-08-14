#!/usr/bin/env python3
"""
Lighthouse performance score model.

Reproduces Lighthouse 10+ scoring: each metric is scored against a log-normal curve defined by
its p10 (score 90) and median (score 50) control points, then weighted and summed.

Validation: fed the metrics from the badgemyauto.com PageSpeed reports (Aug 14 2026) it returns
exactly 72 (desktop) and 45 (mobile), matching the published scores.

Use it to answer "if I get TBT to X, what do I score?" BEFORE doing the work.

    python3 lighthouse-score-model.py
"""

import math

# metric: (weight, p10_mobile, median_mobile, p10_desktop, median_desktop)
CURVES = {
    'FCP': (0.10, 1800, 3000, 934, 1600),
    'SI':  (0.10, 3387, 5800, 1311, 2300),
    'LCP': (0.25, 2500, 4000, 1200, 2400),
    'TBT': (0.30, 200, 600, 150, 350),
    'CLS': (0.25, 0.1, 0.25, 0.1, 0.25),
}


def metric_score(value, p10, median):
    """Log-normal curve: score 0.9 at p10, 0.5 at median."""
    mu = math.log(median)
    sigma = (math.log(p10) - mu) / -1.2816   # z-score for the 90th percentile
    z = (math.log(value) - mu) / sigma
    cdf = 0.5 * (1 + math.erf(z / math.sqrt(2)))
    return max(0.0, min(1.0, 1 - cdf))


def total(values, desktop):
    """Return (overall_score, {metric: (metric_score, points_contributed)})."""
    score, parts = 0.0, {}
    for name, (weight, p10_m, med_m, p10_d, med_d) in CURVES.items():
        p10, median = (p10_d, med_d) if desktop else (p10_m, med_m)
        s = metric_score(values[name], p10, median)
        parts[name] = (round(s * 100), round(weight * s * 100, 1))
        score += weight * s * 100
    return round(score), parts


def required(metric, values, desktop, target):
    """Binary-search the largest value of `metric` that still reaches `target` overall."""
    lo, hi = 1.0, 20000.0
    for _ in range(60):
        mid = (lo + hi) / 2
        probe = dict(values, **{metric: mid})
        if total(probe, desktop)[0] >= target:
            lo = mid
        else:
            hi = mid
    return round(lo)


def report(label, values, desktop):
    score, parts = total(values, desktop)
    print(f'\n{label} = {score}')
    for name, (s, points) in parts.items():
        avail = CURVES[name][0] * 100
        print(f'   {name:4} {values[name]:>8} -> metric {s:3}/100, contributes {points:5}/{avail:.0f}')


if __name__ == '__main__':
    # Measured on badgemyauto.com, Aug 14 2026. Edit these to model a scenario.
    mobile = {'FCP': 6300, 'SI': 7200, 'LCP': 15800, 'TBT': 550, 'CLS': 0.001}
    desktop = {'FCP': 500, 'SI': 1000, 'LCP': 1300, 'TBT': 640, 'CLS': 0.006}

    print('=== CURRENT ===')
    report('MOBILE', mobile, desktop=False)
    report('DESKTOP', desktop, desktop=True)

    print('\n=== DESKTOP: TBT needed for a 95, at various LCP ===')
    for lcp in (1300, 1100, 900, 800):
        probe = dict(desktop, LCP=lcp)
        print(f'   LCP {lcp}ms -> TBT must be <= {required("TBT", probe, True, 95)}ms')

    print('\n=== MOBILE: a budget that clears 95 ===')
    report('TARGET', {'FCP': 1500, 'SI': 3000, 'LCP': 2200, 'TBT': 150, 'CLS': 0.01}, desktop=False)

    print('\n=== MOBILE: a typical "well-optimised store with apps" ===')
    report('REALISTIC', {'FCP': 2200, 'SI': 4000, 'LCP': 3000, 'TBT': 250, 'CLS': 0.01}, desktop=False)
    print('\nNote the gap: good-but-not-perfect mobile scores 86, not 95.')
