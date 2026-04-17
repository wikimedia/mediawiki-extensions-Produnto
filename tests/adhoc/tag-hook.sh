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
  "after": "889c1d5dfc7de780783cf1d4500ba1bcfcfcb8b4",
  "ref": "refs/tags/v1.3",
  "ref_protected": false,
  "checkout_sha": "889c1d5dfc7de780783cf1d4500ba1bcfcfcb8b4",
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
      "id": "889c1d5dfc7de780783cf1d4500ba1bcfcfcb8b4",
      "message": "Add a JSON file\n",
      "title": "Add a JSON file",
      "timestamp": "2026-03-11T14:13:34+11:00",
      "url": "https://gitlab.wikimedia.org/tstarling/produnto-test/-/commit/889c1d5dfc7de780783cf1d4500ba1bcfcfcb8b4",
      "author": {
        "name": "Tim Starling",
        "email": "[REDACTED]"
      },
      "added": [
        "src/test.json"
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

