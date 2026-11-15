#!/usr/bin/env python3
"""Build the S-14 example run fixture from realistic-data.json (A07, decision 63).

Output: app/Modules/Proving/Domain/example-run.json, shaped like contracts
§3.3 (RunHeader, RunDeliveryRow, FindingView) plus the documented example
extras (scenario cells, inspector fields). Rerun after realistic-data changes:

    python3 project/design/gen_example_run_fixture.py && npx vp fmt app/Modules/Proving/Domain/example-run.json
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[2]
data = json.loads((ROOT / 'project/design/realistic-data.json').read_text())
run = data['run']
names = {s['key']: s['name'] for s in data['scenarios']}
findings_by_id = {f['id']: f for f in run['findings']}
sev_rank = {'critical': 0, 'high': 1, 'medium': 2, 'info': 3}
d_num = lambda label: int(label[1:])
f_num = lambda fid: int(fid[1:])


def disp(v):
    return None if v is None else str(v)


deliveries = []
for x in run['deliveries']:
    p = x['probe']
    readings = []
    if p['before'] is not None or p['after'] is not None:
        readings.append({
            'name': p['key'],
            'changed': p['before'] != p['after'],
            'matchesExpected': None if p['expected'] is None else p['after'] == p['expected'],
            'flagged': p['flagged'],
            'display': {'before': disp(p['before']), 'after': disp(p['after']), 'expected': disp(p['expected'])},
        })
    resp = x['response']
    ctype = next((v for k, v in resp['headers'] if k.lower() == 'content-type'), None)
    deliveries.append({
        'd': x['seq'],
        'scenario': x['scenario'],
        'sourceSeq': int(x['source_label'].lstrip('#')),
        'mode': x['mode'],
        'offsetMs': x['sent_rel_ms'],
        'signing': {'kind': x['signing']},
        'tamper': x['tamper'],
        'status': resp['status'],
        'transport': None,
        'latencyMs': resp['latency_ms'],
        'overBudget': not x['within_budget'],
        'response': {'contentType': ctype, 'bytes': len(resp['body_snippet'].encode()), 'sha256': ''},
        'state': {'readings': readings, 'failed': False, 'unsettled': False},
        'result': x['verdict'],
        # Example extras (G2 inspector fields): what the inspector shows.
        'eventType': x['event_type'],
        'finding': f_num(x['finding']) if x['finding'] else None,
        'budgetJudged': x['over_budget_counts'],
        'digest': p['digest'],
        'inspector': {
            'sentAt': x['sent_at'],
            'completedMs': x['completed_rel_ms'],
            'request': {
                'method': x.get('request', {}).get('method', 'POST'),
                'url': x.get('request', {}).get('url', run['target']['url']),
                'headers': x.get('request', {}).get('headers', []),
                'bodyNote': x.get('request', {}).get('body_note'),
                'sizeBytes': x.get('request', {}).get('size_bytes'),
            },
            'response': {'headers': resp['headers'], 'body': resp['body_snippet']},
        },
    })

cells = []
for s in run['scenario_results']:
    fids = s.get('findings', [])
    counted = [f for f in fids if findings_by_id[f]['severity'] != 'info']
    worst = sorted(fids, key=lambda f: sev_rank[findings_by_id[f]['severity']])
    cells.append({
        'key': s['key'],
        'name': names[s['key']],
        'result': s['result'],
        'deliveries': [d_num(d) for d in s['deliveries']],
        'findings': [{'n': f_num(f), 'severity': findings_by_id[f]['severity']} for f in worst],
        'counted': len(counted),
        'reading': s['reading'],
        'line': s['line'],
    })

findings = []
for f in run['findings']:
    findings.append({
        'n': f_num(f['id']),
        'type': f['type'],
        'severity': f['severity'],
        'level': f['level'],
        'title': f['title'],
        'eventType': f['event_type'],
        'scenarios': [names[k] for k in f['scenarios']],
        'evidence': [{'d': d_num(e), 'role': f['evidence_roles'].get(e, 'offending')} for e in f['evidence']],
        'what': f['summary'],
        'why': f['why'],
        'citation': {'label': f['citation'], 'url': 'https://docs.stripe.com/webhooks'} if f.get('citation') else None,
        'fix': {'text': f['fix_note'], 'framework': f.get('fix_framework'), 'snippet': f.get('fix')},
        'likelyCause': {'rule': f['cause_rule'], 'text': f['cause_hint']} if f.get('cause_rule') else None,
        'fingerprint': f"example-{f['id'].lower()}",
        'seenBefore': False,
        # Example extras.
        'levelLabel': f['level_label'],
        'caption': f.get('caption'),
        'measured': f.get('measured'),
    })

target = run['target']['url']
scheme, rest = target.split('://')
hostport, path = rest.split('/', 1)
host, port = hostport.split(':')
group = data['groups'][0]
example_copy = data['copy']['errors']['example']

fixture = {
    'run': {
        'id': 'example',
        'number': run['number'],
        'status': run['status'],
        'reason': run['status_reason'],
        'exitCode': run['exit_code'],
        'provenance': {'executor': 'agent', 'source': 'web', 'agentName': run['provenance']['agent_name']},
        'agent': {'name': data['agent']['name'], 'version': data['agent']['version']},
        'provider': 'stripe',
        'pack': {'id': 'stripe', 'version': '1'},
        'budget': {'ms': run['budget_ms'], 'source': run['budget_source'], 'label': run['budget_label']},
        'target': {'scheme': scheme, 'host': host, 'port': int(port), 'path': '/' + path},
        'levels': [1, 2],
        'probeEntries': [{'name': e['name'], 'displayMode': e['display_mode']} for e in run['probe']['entries']],
        'counts': {k: run['counts'][k] for k in ('critical', 'high', 'medium', 'info', 'findings')},
        'from': {'groupKey': group['short'], 'seqs': [1, 3, 4]},
        'startedAt': run['started_at'],
        'finishedAt': run['finished_at'],
        'durationMs': run['duration_ms'],
        'notChecked': run['not_checked'],
        'orderDependent': False,
        'visibility': 'private',
        # Example extras.
        'title': run['title'],
        'project': data['project']['name'],
        'framework': run['target']['framework_detected'],
        'failOn': run['fail_on'],
        'burstSpreadMs': run['burst']['dispatch_spread_ms'],
    },
    'scenarios': cells,
    'timeline': {'lastSeq': len(deliveries), 'status': run['status'], 'deliveries': deliveries},
    'findings': findings,
    'example': {'label': example_copy['label'], 'helper': example_copy['helper']},
}

out = ROOT / 'app/Modules/Proving/Domain/example-run.json'
out.write_text(json.dumps(fixture, ensure_ascii=False, indent=2) + '\n')
print(f'wrote {out.relative_to(ROOT)}: {len(deliveries)} deliveries, {len(findings)} findings')
