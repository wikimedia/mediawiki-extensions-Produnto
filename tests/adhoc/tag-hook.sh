#!/bin/bash

curl -vvv -w\\n -k https://mw.internal/rest.php/produnto/v1/gitlab/tag \
-H'Content-Type: application/json' \
-H'User-Agent: GitLab/18.9.1' \
-H'Idempotency-Key: e5eaa373-7358-400b-847f-cca1e9869287' \
-H'X-Gitlab-Event: Tag Push Hook' \
-H'X-Gitlab-Webhook-Uuid: 28e9a8d5-98a8-4313-8ef7-5275dbc62c54' \
-H'X-Gitlab-Instance: https://gitlab.wikimedia.org' \
-H'X-Gitlab-Event-Uuid: 0d518e32-befb-4d5e-a039-6ad029b96b11' \
--data-binary @- \
<<END
{
  "object_kind": "tag_push",
  "event_name": "tag_push",
  "before": "0000000000000000000000000000000000000000",
  "after": "74ad0510ddc00c5ef615fa0faf88b5ec22abd8c1",
  "ref": "refs/tags/v1.7",
  "ref_protected": false,
  "checkout_sha": "74ad0510ddc00c5ef615fa0faf88b5ec22abd8c1",
  "message": "",
  "user_id": 129,
  "user_name": "Tim Starling",
  "user_username": "tstarling",
  "user_email": "",
  "user_avatar": null,
  "project_id": 4127,
  "project": {
    "id": 4127,
    "name": "produnto-test",
    "description": null,
    "web_url": "https://gitlab.wikimedia.org/tstarling/produnto-test",
    "avatar_url": null,
    "git_ssh_url": "git@gitlab.wikimedia.org:tstarling/produnto-test.git",
    "git_http_url": "https://gitlab.wikimedia.org/tstarling/produnto-test.git",
    "namespace": "Tim Starling",
    "visibility_level": 20,
    "path_with_namespace": "tstarling/produnto-test",
    "default_branch": "main",
    "ci_config_path": "",
    "homepage": "https://gitlab.wikimedia.org/tstarling/produnto-test",
    "url": "git@gitlab.wikimedia.org:tstarling/produnto-test.git",
    "ssh_url": "git@gitlab.wikimedia.org:tstarling/produnto-test.git",
    "http_url": "https://gitlab.wikimedia.org/tstarling/produnto-test.git"
  },
  "commits": [
    {
      "id": "74ad0510ddc00c5ef615fa0faf88b5ec22abd8c1",
      "message": "Add invalid title test case\n",
      "title": "Add invalid title test case",
      "timestamp": "2026-06-15T14:06:02+10:00",
      "url": "https://gitlab.wikimedia.org/tstarling/produnto-test/-/commit/74ad0510ddc00c5ef615fa0faf88b5ec22abd8c1",
      "author": {
        "name": "Tim Starling",
        "email": "[REDACTED]"
      },
      "added": [
        "src/invalid-title##.lua"
      ],
      "modified": [],
      "removed": []
    }
  ],
  "total_commits_count": 1,
  "push_options": {},
  "repository": {
    "name": "produnto-test",
    "url": "git@gitlab.wikimedia.org:tstarling/produnto-test.git",
    "description": null,
    "homepage": "https://gitlab.wikimedia.org/tstarling/produnto-test",
    "git_http_url": "https://gitlab.wikimedia.org/tstarling/produnto-test.git",
    "git_ssh_url": "git@gitlab.wikimedia.org:tstarling/produnto-test.git",
    "visibility_level": 20
  }
}
END

