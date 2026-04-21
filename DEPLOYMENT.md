# Chrysalis Deployment Map

## Canonical repo
This repository is the single source of truth for the Chrysalis codebase.

It may contain:
- private/
- public_html/
- chrys-scripts/
- .github/
- repo-level support files such as .gitignore and deployment metadata

## Track and deploy
These paths are normally synchronised to the live server:

- public_html/pecherie/**
- private/**

## Track only
These paths stay in the canonical repo but are not part of the default live deploy:

- .github/**
- .gitignore
- chrys-scripts/** unless explicitly needed on the server
- private/docs/** if these are documentation-only and not needed by runtime

## Do not track and do not deploy
These should remain outside version control and outside deployment:

- .idea/**
- **/*.bak
- **/error_log
- hello.txt
- local-only scratch files
- ignored secret and env files

## Server-only files
These are managed on the server and must not be overwritten by normal deployment:

- pecherie_config.php
- private/chrysalis-slack.env

## Deployment rules
- Deployment is curated, not full-repo copy.
- GitHub is the source of truth for tracked application code.
- FTP is for inspection or emergency use, not normal deployment.
- Server-only config must remain untouched unless intentionally updated.