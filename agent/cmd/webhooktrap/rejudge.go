package main

import (
	"bytes"
	"encoding/json"
	"fmt"

	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/render"
)

// runRejudge recomputes the verdict from a local run file's plan and
// observations and checks it is identical (JSON-4).
func runRejudge(args []string, e env) int {
	if len(args) != 1 {
		fmt.Fprintln(e.stderr, "usage: webhooktrap rejudge <run.json>")
		return 3
	}
	raw, err := readRegular(args[0], 4<<20)
	if err != nil {
		fmt.Fprintln(e.stderr, "rejudge: "+clean(err.Error()))
		return 3
	}
	var doc render.Document
	if err := json.Unmarshal(raw, &doc); err != nil || doc.Plan == nil || doc.Observations == nil {
		fmt.Fprintln(e.stderr, "rejudge: not a local run document")
		return 3
	}
	pk, err := e.packs.Load(doc.Run.Pack.ID, doc.Run.Pack.Version)
	if err != nil {
		fmt.Fprintln(e.stderr, "rejudge: "+clean(err.Error()))
		return 3
	}
	doc.Plan.Hash = doc.Run.PlanHash
	opt := judge.Options{FailOn: doc.Run.FailOn, FailOnInconclusive: doc.Run.FailOnInconclusive, TargetPath: doc.Run.Target.Path, Covers: map[string][]string{}}
	if doc.Run.Framework != nil && doc.Run.Framework.DetectedFrom == "webhooktrap_yml" {
		opt.Framework = doc.Run.Framework.Name
	}
	for _, p := range doc.Run.ProbeEntries {
		opt.Covers[p.Name] = p.Covers
	}
	v := judge.Judge(doc.Plan, doc.Observations, pk, opt)
	var want []judge.Finding
	for _, f := range doc.Findings {
		want = append(want, f.Finding)
	}
	a, _ := json.Marshal(struct {
		S string
		F []judge.Finding
	}{v.Status, v.Findings})
	b, _ := json.Marshal(struct {
		S string
		F []judge.Finding
	}{doc.Run.Status, want})
	if v.Findings == nil && want == nil || bytes.Equal(a, b) {
		fmt.Fprintf(e.stdout, "rejudge: identical (%s, %d findings)\n", v.Status, len(v.Findings))
		return 0
	}
	fmt.Fprintln(e.stdout, "rejudge: verdict differs from the run file")
	return 1
}
