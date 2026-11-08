# Bash scripts on shared hosting

## `/dev/fd/N` and process substitution

Bash process substitution lets a command expose its input or output as a
file-like path:

```bash
mapfile -t entries < <(find "$directory" -type f)
command > >(tee -a "$log_file")
```

Bash commonly implements those paths through `/dev/fd/N` (or `/proc/self/fd`).
On some shared-hosting shells that file-descriptor interface is unavailable or
not mounted for the non-interactive shell. The script then fails with an error
such as:

```text
line 119: /dev/fd/63: No such file or directory
```

This is a shell/runtime compatibility problem. It does not by itself indicate
that the input file, database, or command named in the script is missing.

When a hosting script reports `/dev/fd/N`, inspect it for `<(...)` and
`>(...)`. Prefer ordinary files and redirections in scripts that must run on
shared hosting. For example, write command output to a temporary file and then
read it with a normal input redirection, or redirect the run log to a file and
display that file after the command completes. Temporary files should be
created in a known writable directory and removed during cleanup.

The SAM full-sync runner follows this approach: it writes the run log directly
to `~/sam/logs/`, uses a file for the discovered XML paths, then prints the log
after the run. It does not require `/dev/fd` process substitution.
