#!/usr/bin/env bash
set -euo pipefail
umask 077
export LC_ALL=C

mysql_volume=k4-parser_sail-mysql
redis_volume=k4-parser_sail-redis

archive_volume() {
    local context=$1 image=$2 volume=$3
    shift 3
    docker --context "$context" run --rm -i --pull never --network none --read-only \
        --mount "type=volume,source=$volume,target=/var/lib/mysql,readonly,volume-nocopy" \
        --entrypoint tar "$image" -C /var/lib/mysql "$@"
}

backup_volume() {
    local service=$1 volume=$2 mount_path=$3 image=$4 archive=$5
    local container="k4-parser-$service-1"
    local state attachments running
    state=$(docker --context desktop-linux inspect --format '{{.State.Status}}:{{.State.ExitCode}}' "$container")
    attachments=$(docker --context desktop-linux inspect --format '{{range .Mounts}}{{println .Name .Destination}}{{end}}' "$container")
    running=$(docker --context desktop-linux ps -q --filter "volume=$volume")
    if [[ $state != exited:0 || -n $running ]] || ! grep -Fxq "$volume $mount_path" <<< "$attachments"; then
        printf 'Expected an unused volume %s on a cleanly stopped %s.\n' "$volume" "$container" >&2
        exit 1
    fi
    docker --context desktop-linux volume inspect --format '{{.Name}} {{.CreatedAt}}' "$volume"
    archive_volume desktop-linux "$image" "$volume" --numeric-owner --create --file - . > "$archive"
    archive_volume desktop-linux "$image" "$volume" --numeric-owner --compare --file - < "$archive"
}

restore_volume() {
    local image=$1 volume=$2 archive=$3
    docker --context colima run --rm -i --pull never --network none --read-only \
        --mount "type=volume,source=$volume,target=/var/lib/mysql,volume-nocopy" \
        --entrypoint sh "$image" -ec '
            if [ -n "$(ls -A /var/lib/mysql)" ]; then
                printf "The restore volume is not empty.\n" >&2
                exit 1
            fi
            exec tar -C /var/lib/mysql --numeric-owner --extract --file -
        ' < "$archive"
    archive_volume colima "$image" "$volume" --numeric-owner --compare --file - < "$archive"
    printf 'Verified restored volume %s\n' "$volume"
}

case ${1:-} in
    backup)
        repo=$(cd "$(dirname "$0")/.." && pwd)
        backup=$(mktemp -d "${2:?Provide an existing private backup directory}/k4-parser.XXXXXXXX")
        printf 'Backup directory %s\n' "$backup"
        cd "$backup"

        image=$(docker --context desktop-linux inspect --format '{{.Image}}' k4-parser-mysql-1)
        backup_volume mysql "$mysql_volume" /var/lib/mysql "$image" mysql.tar
        backup_volume redis "$redis_volume" /data "$image" redis.tar
        tar -tf mysql.tar > mysql-files.txt
        tar -tf redis.tar > redis-files.txt
        grep -Fxq './mysql.ibd' mysql-files.txt

        sqlite3 -batch -bail -readonly "$repo/database/database.sqlite" '.timeout 5000' '.backup sqlite.db'
        [[ $(sqlite3 -batch -bail -readonly sqlite.db 'PRAGMA integrity_check;') == ok ]]
        shasum -a 256 mysql.tar redis.tar sqlite.db > SHA256SUMS
        shasum -a 256 -c SHA256SUMS
        ;;
    restore-new)
        cd "${2:?Provide a verified backup directory}"
        suffix=${3:?Provide a unique suffix for the new volumes}
        if [[ ! $suffix =~ ^[a-z0-9][a-z0-9-]*$ ]]; then
            printf 'Use lowercase letters, digits, and hyphens for the suffix.\n' >&2
            exit 1
        fi
        shasum -a 256 -c SHA256SUMS
        image=$(docker --context colima image inspect mysql:8.4 --format '{{.Id}}')
        existing=$(docker --context colima volume ls --format '{{.Name}}')
        mysql_restore="k4-parser-desktop-$suffix-mysql"
        redis_restore="k4-parser-desktop-$suffix-redis"
        if grep -Fxq -e "$mysql_restore" -e "$redis_restore" <<< "$existing"; then
            printf 'A restore volume already exists. Choose a new suffix.\n' >&2
            exit 1
        fi

        restore_volume "$image" "$mysql_restore" mysql.tar
        restore_volume "$image" "$redis_restore" redis.tar
        ;;
    *)
        printf 'Usage: bash %s backup BACKUP_PARENT | restore-new BACKUP_DIRECTORY UNIQUE_SUFFIX\n' "$0" >&2
        exit 2
        ;;
esac
