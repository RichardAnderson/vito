curl {{ $passive }} --ftp-create-dirs -T "{{ $src }}" -u "{{ $username }}:{{ $password }}" ftp{{ $ssl }}://{{ $host }}:{{ $port }}/{{ $dest }}
