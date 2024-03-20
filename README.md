stream wrapper interface
====

## Description

php のストリームラッパーを規約するパッケージです。

php のストリームラッパーは非常に強力かつ柔軟性があるのですが、いかんせん使い方が難しく、ドキュメントを読んでもイマイチ実装方法が分かりません。
また、実装するにしてもインターフェースが切られておらず、ドキュメントと悪戦苦闘して試行錯誤しながら実装する必要があります。
さらにいくつかは（一見関連がなさそうに見えるものの）同じメソッドで引数分岐なものがあり、依存関係が非常に分かりにくいです。

そこで、インターフェースを宣言し、決められたメソッドを実装すればストリームラッパーとして動作する interface と trait をあらかじめ用意しておく、のがこのパッケージの主旨です。

## Install

```json
{
  "require": {
    "ryunosuke/stream-wrapper": "dev-master"
  }
}
```

## Feature

### ディレクトリ構成

```
├── Exception/
├── Utils/
├── Stream/
├── Mixin
│   ├── DirectoryIOTrait.php
│   ├── DirectoryIteratorTrait.php
│   ├── StreamTrait.php
│   ├── UrlIOTrait.php
│   └── UrlPermissionTrait.php
├── PhpStreamWrapperInterface.php
├── StreamWrapperAdapterInterface.php
├── StreamWrapperAdapterTrait.php
└── StreamWrapperNoopTrait.php
```

#### Top Directory

これがこのパッケージの目的です。**他はすべておまけです**。

##### PhpStreamWrapperInterface

php オリジナルのストリームラッパーを interface として切り出したものです。

おそらくですが、 php 本体がインターフェースを用意しない理由として**一部のメソッドは実装してはならない**というものがあると思います。
例えば mkdir( https://www.php.net/manual/streamwrapper.mkdir.php )などは「適切なエラーメッセージを返すためには、ラッパーがディレクトリの作成に対応していない場合にはこのメソッドを定義してはいけません」とあります。他にもいくつか同様の注意書きが書かれたメソッドがあります。
この言葉を信じるならば「適切なエラーメッセージを返すため」程度の話であり、エラーメッセージのためにインターフェースが切れないのはすこぶる不便です。
のでこのパッケージでは例外で対応する、と割り切ることでインターフェースを用意しています。

まぁ実際のところ必要ありません。本家が interface を用意していない以上、別に使わずともエラーにはならないためです。
実行時エラーではなくコンパイル時エラーにしたいため、主に開発時のために用意されています。

##### StreamWrapperAdapterInterface

本パッケージにおけるストリームラッパーを規約する interface です。

前述の通り、標準ストリームラッパーはメソッド名が分かりにくかったり、妙な場合分けがあったり、唐突に context という概念（しかもリソース）が出てきたりして、使いやすいとは言えないので、この interface で「関数名とメソッド名が一致するようなインターフェース」を切り、それを StreamWrapperAdapterTrait で本家と接続します。
これによって context や場合分けなどはすべて吸収され、簡潔なメソッド群とそれに対応する関数がマップされる構造になります。

##### StreamWrapperAdapterTrait

StreamWrapperAdapterInterface と PhpStreamWrapperInterface を接続する trait です。

StreamWrapperAdapterInterface は要するにオレオレ規約を設けているだけなのでそれだけではストリームラッパーとして動きません。
動かすためには PhpStreamWrapperInterface の実装とそれらを繋ぐボイラープレートが必要になります。
それを担う trait です。

返り値にユニオン型はほとんど使いません（今のところ readdir だけが特例）。
しかるべき値が返せない場合は ErrorException を投げれば php のエラーに変換されるようになっています。

context という概念は抹消されます。context はパースされて、そのオプション配列として引数で渡ってきます。
context にはさらに options と params という概念があるため、2つの引数を受け取ります。
もっとも、 params に関してはドキュメントが非常に乏しく、使うことはほとんどないでしょう（公式ドキュメントでも stream_notification_callback という形で一例しか使われていません）。

##### StreamWrapperNoopTrait

StreamWrapperAdapterInterface のメソッドをすべて実装していますが、すべて例外を投げる trait です。

ストリームラッパーをいざ実装しようとすると使わないメソッドまで実装する必要が出てくるため、それを補う意味で全メソッドで呼ぶと例外が発生する実装になっています（一部特例あり）。
これを use して、実装側クラスで必要なものだけを実装すればそのクラスはストリームラッパーとして動作させることができる、ということです。

-----

```php
class HogeStream implements
    PhpStreamWrapperInterface,    // これにより、php オリジナルのストリームラッパーインターフェースがすべて規約されます（なくても動きますが、エラーが実行時になります）
    StreamWrapperAdapterInterface // これにより、オレオレストリームラッパーインターフェースがすべて規約されます
{
    use StreamWrapperAdapterTrait; // オレオレストリームラッパーと php オリジナルのストリームラッパーを接続させるための trait です
    use StreamWrapperNoopTrait;    // すべてが例外を投げるデフォルト実装 trait です

    public function _stat(string $url): array // これを実装すれば stat(filesize や filemtime) を実装したことになります
    {
        // ...
    }
}
```

下記はメソッドの詳細です。一部改変しています。
全部読むとストリームラッパーのあまりにもカオスな状態が分かると思います。

###### `_mkdir`

- function: [`mkdir(string $directory, int $permissions = 0777, bool $recursive = false, ?resource $context = null): bool`](https://www.php.net/manual/function.mkdir.php)
- StreamWrapper: [`mkdir(string $path, int $mode, int $options): bool`](https://www.php.net/manual/streamwrapper.mkdir.php)
- ThisPackage: `_mkdir(string $url, int $permissions, bool $recursive, array $contextOptions, array $contextParams): bool`

関数版は引数が個別で分かれていますが、ストリーム版はビットフラグで流れてくるため、バラしてから呼び出します。
つまり、インターフェースとしては関数版 mkdir と同じです（context 以外）。

###### `_rmdir`

- function: [`rmdir(string $directory, ?resource $context = null): bool`](https://www.php.net/manual/function.rmdir.php)
- StreamWrapper: [`rmdir(string $path, int $options): bool`](https://www.php.net/manual/streamwrapper.rmdir.php)
- ThisPackage: `_rmdir(string $url, array $contextOptions, array $contextParams): bool`

実装すべきストリーム版に謎の引数 $options がありますが、対応していません。
ドキュメントは存在し、「STREAM_MKDIR_RECURSIVE などの値のビットマスク」とありますが、関数版にそのような引数はありません。
確かに rmdir で再帰的に削除できたら便利な場面もありますが…。これ mkdir のコピペミスなんじゃ…と思っています。

###### `_touch`

- function: [`touch(string $filename, ?int $mtime = null, ?int $atime = null): bool`](https://www.php.net/manual/function.touch.php)
- StreamWrapper: [`stream_metadata(string $path, int $option, mixed $value): bool`](https://www.php.net/manual/streamwrapper.stream-metadata.php)
- ThisPackage: `_touch(string $url, int $mtime, int $atime): bool`

関数版 tocuh に対応するストリーム版メソッドは存在しません。stream_metadata の引数分岐で実装されています。
「touch の場合は…chmod の場合は…」と細かく書かれていますが、interface+trait ですべて吸収してあります。
つまり、インターフェースとしては関数版 touch とほぼ同じです。
「ほぼ」というのは ?int が int になっています。省略時や両方指定時の値はすべて解決されて渡ってきます。

余談ですが、命名規則がおかしく、引数はパスなので stream_metadata ではなく url_metadata の方が正しいと思ってます。
（「ストリームラッパーの関数だから」ということなんでしょうが…。でもだとすると `utl_stat` の説明がつかない）。

###### `_chmod`

- function: [`chmod(string $filename, int $permissions): bool`](https://www.php.net/manual/function.chmod.php)
- StreamWrapper: [`stream_metadata(string $path, int $option, mixed $value): bool`](https://www.php.net/manual/streamwrapper.stream-metadata.php)
- ThisPackage: `_chmod(string $url, int $permissions): bool`

関数版 chmod に対応するストリーム関数は存在しません。stream_metadata の引数分岐で実装されています。
つまり、すべてにおいて touch の説明と同じです。

###### `_chown`

- function: [`chown(string $filename, string|int $user): bool`](https://www.php.net/manual/function.chown.php)
- StreamWrapper: [`stream_metadata(string $path, int $option, mixed $value): bool`](https://www.php.net/manual/streamwrapper.stream-metadata.php)
- ThisPackage: `_chown(string $url, int $uid): bool`

関数版 chown に対応するストリーム関数は存在しません。stream_metadata の引数分岐で実装されています。
つまり、すべてにおいて touch の説明と同じですが、名前引きされてからコールされるので $uid は int です。

###### `_chgrp`

- function: [`chgrp(string $filename, string|int $group): bool`](https://www.php.net/manual/function.chgrp.php)
- StreamWrapper: [`stream_metadata(string $path, int $option, mixed $value): bool`](https://www.php.net/manual/streamwrapper.stream-metadata.php)
- ThisPackage: `_chgrp(string $url, int $gid): bool`

関数版 chgrp に対応するストリーム関数は存在しません。stream_metadata の引数分岐で実装されています。
つまり、すべてにおいて touch の説明と同じですが、名前引きされてからコールされるので $gid は int です。

###### `_unlink`

- function: [`unlink(string $filename, ?resource $context = null): bool`](https://www.php.net/manual/function.unlink.php)
- StreamWrapper: [`unlink(string $path): bool`](https://www.php.net/manual/streamwrapper.unlink.php)
- ThisPackage: `_unlink(string $url, array $contextOptions, array $contextParams): bool`

特に変わり映えしません。

###### `_rename`

- function: [`rename(string $from, string $to, ?resource $context = null): bool`](https://www.php.net/manual/function.rename.php)
- StreamWrapper: [`rename(string $path_from, string $path_to): bool`](https://www.php.net/manual/streamwrapper.rename.php)
- ThisPackage: `_rename(string $src_url, string $dst_url, array $contextOptions, array $contextParams): bool`

特に変わり映えしません。

###### `_stat`

- function: [`stat(string $filename): array|false`](https://www.php.net/manual/function.stat.php)
- StreamWrapper: [`url_stat(string $path, int $flags): array|false`](https://www.php.net/manual/streamwrapper.url-stat.php)
- ThisPackage: `_stat(string $url): array`

特に変わり映えしません。
なお、stat なので仕方ありませんが、このメソッドに対応する関数は非常に多岐に渡ります。
大抵のカスタムラッパーで実装は必須になるでしょう（最低限 size だけでも返す方が望ましい）。

###### `_lstat`

- function: [`lstat(string $filename): array|false`](https://www.php.net/manual/function.lstat.php)
- StreamWrapper: [`url_stat(string $path, int $flags): array|false`](https://www.php.net/manual/streamwrapper.url-stat.php)
- ThisPackage: `_lstat(string $url): array`

対応するストリーム側のメソッドはありません。リンクという概念はファイルシステムの影響が強いため、ストリームラッパーとしてコールされることはほぼないのでしょう。
本パッケージでは関数と1対1に対応させたかったため、敢えて分けています。
のでこのメソッドは特別扱いで、コールすると「未実装例外」ではなくデフォルトで `_stat` へ委譲されます。

###### `_opendir`

- function: [`opendir(string $directory, ?resource $context = null): resource|false`](https://www.php.net/manual/function.opendir.php)
- StreamWrapper: [`dir_opendir(string $path, int $options): bool`](https://www.php.net/manual/streamwrapper.dir-opendir.php)
- ThisPackage: `_opendir(string $url, array $contextOptions, array $contextParams): object`

実装すべきストリーム版に謎の引数 $options がありますが、対応していません。
ドキュメントも存在せず、何のための引数かも不明なためです。
最初は scandir の $sorting_order 引数に対応するかと思ったんですが、どうもそんなこともないようです。

この opendir と fopen はストリームを開く関数のため、内部状態を持たせる必要があります。
本パッケージでは状態を持つことを嫌って、引数で取り回しをしています。
つまり、_opendir で何らかのオブジェクトを返すと、そのオブジェクトが _readdir, _closedir の引数として渡ってきます。
この仕様によって、開いた何かをプロパティなどで保持する処理を不要としています。
使用する際も「fopen で開いたリソースを fread 等に渡す」という使い方のため、この仕様の方が直感的だと思っています。
StreamWrapper を素で実装する際、「fread に引数がないけど fopen したリソースはどうやって取得するの？」となりがちだったのです。

###### `_readdir`

- function: [`readdir(?resource $dir_handle = null): string|false`](https://www.php.net/manual/function.readdir.php)
- StreamWrapper: [`dir_readdir(): string`](https://www.php.net/manual/streamwrapper.dir-readdir.php)
- ThisPackage: `_readdir(object $resource): ?string`

$dir_handle の省略は不要なので対応していません。
php によくある「引数は省略可能で、省略した場合は最後のリソースが使われます」は昔の名残であり、現在ではほとんど不要な仕様だと思います。

あとオリジナルは `string|false` です（※ ドキュメントは string ですが誤りです）が、回しきった場合は null を返します。
これは `?string` と表現できる、という一点の理由のみです。
php8 に移行してユニオン型が使えるようになったら `array|false` にするかもしれませんが（個人的な理由で）かなり先の話です。

###### `_rewinddir`

- function: [`rewinddir(?resource $dir_handle = null): void`](https://www.php.net/manual/function.rewinddir.php)
- StreamWrapper: [`dir_rewinddir(): bool`](https://www.php.net/manual/streamwrapper.dir-rewinddir.php)
- ThisPackage: `_rewinddir(object $resource): bool`

特に変わり映えしません。

###### `_closedir`

- function: [`closedir(?resource $dir_handle = null): void`](https://www.php.net/manual/function.closedir.php)
- StreamWrapper: [`dir_closedir(): bool`](https://www.php.net/manual/streamwrapper.dir-closedir.php)
- ThisPackage: `_closedir(object $resource): bool`

特に変わり映えしません。

###### `_fopen`

- function: [`fopen(string $filename, string $mode, bool $use_include_path = false, ?resource $context = null): resource|false`](https://www.php.net/manual/function.fopen.php)
- StreamWrapper: [`stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool`](https://www.php.net/manual/streamwrapper.stream-open.php)
- ThisPackage: `_fopen(string $url, string $mode, array $contextOptions, array $contextParams): object`

一部謎の引数がありますが、すべて吸収してあるので、インターフェースとしては `fopen` とほぼ同じです。
返り値については `_opendir` と同じことが言え、この `_fopen` で返したオブジェクトが `_fread` や `_fwrite` 等のメソッドの引数として渡ってきます。

下記は改変部分です。

まず $options ですが、`STREAM_USE_PATH` は字のごとくです。`foepn` や `file_get_contents` の第3引数に対応します。
…が、このパッケージではほぼ対応しておらず、引数も渡しません。
というのも、カスタムストリームラッパーの特性上、$path はすべてスキームから始まるフルパスであり、相対パスが流れてくることがありません。
また、インクルードパスは file スキームを前提に考えられており、カスタムストリームの相対パスがインクルードパスに設定されているという状況自体がまずあり得ません。
（超限定的な状況で fopen がスキームなしで呼ばれることはあるため STREAM_USE_PATH はそのための機能なのかもしれませんが、ここでは割愛します）。

次に `STREAM_REPORT_ERRORS` ですが、これが立って渡ってくる状況をほとんど見つけることができませんでした。
一応は対応しており、このフラグが立ってないと警告は抑止されるようになってます（ので fopen 自体のエラーは報告されるが、詳細なエラーが分からなくなります）。
php-src を確認したところ、内部的に `REPORT_ERRORS` はそこそこ使用されているようですが、「別の目的のためにファイルを読み込む必要がある場合（≒ファイルの open が主目的でない場合）」に抑制されている傾向があるようでした。
例えば finfo や exif 等です。
これに関しては非常に不便なため常にエラー出力が為されるように修正される可能性があります。

`$opened_path` はほぼ未対応です。
これはインクルードパスから見つけた場合のフルパスのレシーバ引数ですが、上記の通りその状況自体がほぼ存在しないためです。

###### `_fread`

- function: [`fread(resource $stream, int $length): string|false`](https://www.php.net/manual/function.fread.php)
- StreamWrapper: [`stream_read(int $count): string|false`](https://www.php.net/manual/streamwrapper.stream-read.php)
- ThisPackage: `_fread(object $resource, int $length): string`

特に変わり映えしません。
敢えて言うならドキュメントの注意書きが多いので要注意といったところでしょうか。

- 戻り値が count より長い場合は E_WARNING エラーが発生し、余分なデータは失われます。
- streamWrapper::stream_eof() は、 streamWrapper::stream_read() がコールされた後に直接コールされ、 EOF に達したかどうかを調べます。実装されていない場合は EOF だとみなされます。
- ファイル全体を (file_get_contents() などで) 読み込む場合、PHP はループ内で streamWrapper::stream_read() をコールしてから streamWrapper::stream_eof() をコールします。 しかし、streamWrapper::stream_read() が空でない文字列を返す限りは streamWrapper::stream_eof() の戻り値を無視します。

これは php の仕様であり、パッケージ側でどうにかできる話ではないため引用に留めます。

###### `_fwrite`

- function: [`fwrite(resource $stream, string $data, ?int $length = null): int|false`](https://www.php.net/manual/function.fwrite.php)
- StreamWrapper: [`stream_write(string $data): int`](https://www.php.net/manual/streamwrapper.stream-write.php)
- ThisPackage: `_fwrite(object $resource, string $data): int`

特に変わり映えしません。

###### `_ftruncate`

- function: [`ftruncate(resource $stream, int $size): bool`](https://www.php.net/manual/function.ftruncate.php)
- StreamWrapper: [`stream_truncate(int $new_size): bool`](https://www.php.net/manual/streamwrapper.stream-truncate.php)
- ThisPackage: `_ftruncate(object $resource, int $size): bool`

特に変わり映えしません。

###### `_fclose`

- function: [`fclose(resource $stream): bool`](https://www.php.net/manual/function.fclose.php)
- StreamWrapper: [`stream_close(): void`](https://www.php.net/manual/streamwrapper.stream-close.php)
- ThisPackage: `_fclose(object $resource): bool`

特に変わり映えしません。
敢えて言うなら戻り値を bool にしています。
これは [AWS も嘆いている](https://docs.aws.amazon.com/ja_jp/sdk-for-php/v3/developer-guide/s3-stream-wrapper.html)んですが、もしかしたら将来変更されるかも…と淡い期待を抱いて bool としています。

###### `_ftell`

- function: [`ftell(resource $stream): int|false`](https://www.php.net/manual/function.ftell.php)
- StreamWrapper: [`stream_tell(): int`](https://www.php.net/manual/streamwrapper.stream-tell.php)
- ThisPackage: `_ftell(object $resource): int`

特に変わり映えしません。
敢えて言うならドキュメントでは「このメソッドは、fseek() に対応してコールされ、現在の位置を決定します」とありますが、これは**間違いではありません**。
php のメーリングリスト（URL 失念）を辿った結果、深遠な理由でこうなっていてこう書かれているようなのでこれが正となります。
これに関しては seek の方で少し触れます。

###### `_fseek`

- function: [`fseek(resource $stream, int $offset, int $whence = SEEK_SET): int`](https://www.php.net/manual/function.fseek.php)
- StreamWrapper: [`stream_seek(int $offset, int $whence = SEEK_SET): bool`](https://www.php.net/manual/streamwrapper.stream-seek.php)
- ThisPackage: `_fseek(object $resource, int $offset, int $whence): bool`

特に変わり映えしません。
敢えて言うならドキュメントの「現在の実装は、 whence の値を SEEK_CUR に設定することはありません。 そのようなシークは、 内部的に SEEK_SET と同じ動きに変換されます」は誤りです。
確認したところ少なくとも Windows ではファイルフラグ "a" で fopen すると `SEEK_CUR` でコールされることが確認できました。
つまり SEEK_CUR の実装の必要があるということです。

ちなみに `_ftell` で触れた tell と seek の件は下記が参考になるでしょう。

- 成功した場合、 streamWrapper::stream_seek() をコールした直後に streamWrapper::stream_tell() がコールされます。 streamWrapper::stream_tell() が失敗すると、 呼び出し元関数への戻り値は false に設定されます。

###### `_feof`

- function: [`feof(resource $stream): bool`](https://www.php.net/manual/function.feof.php)
- StreamWrapper: [`stream_eof(): bool`](https://www.php.net/manual/streamwrapper.stream-eof.php)
- ThisPackage: `_feof(object $resource): bool`

特に変わり映えしません。

###### `_fflush`

- function: [`fflush(resource $stream): bool`](https://www.php.net/manual/function.fflush.php)
- StreamWrapper: [`stream_flush(): bool`](https://www.php.net/manual/streamwrapper.stream-flush.php)
- ThisPackage: `_fflush(object $resource): bool`

特に変わり映えしません。

###### `_flock`

- function: [`flock(resource $stream, int $operation, int &$would_block = null): bool`](https://www.php.net/manual/function.flock.php)
- StreamWrapper: [`stream_lock(int $operation): bool`](https://www.php.net/manual/streamwrapper.stream-lock.php)
- ThisPackage: `_flock(object $resource, int $operation): bool`

「ロックがブロックされた」場合のレシーバ引数 `$would_block` は対応していません。
ストリーム側に対応する引数が存在しないため、呼び元に値を伝える術がないためです。

###### `_fstat`

- function: [`fstat(resource $stream): array|false`](https://www.php.net/manual/function.fstat.php)
- StreamWrapper: [`stream_stat(): array|false`](https://www.php.net/manual/streamwrapper.stream-stat.php)
- ThisPackage: `_fstat(object $resource): array`

特に変わり映えしません。

###### `_stream_set_blocking`

- function: [`stream_set_blocking(resource $stream, bool $enable): bool`](https://www.php.net/manual/function.stream-set-blocking.php)
- StreamWrapper: [`stream_set_option(int $option, int $arg1, int $arg2): bool`](https://www.php.net/manual/streamwrapper.stream-set-option.php)
- ThisPackage: `_stream_set_blocking(object $resource, bool $enable): bool`

これも touch などと同様、引数分岐なので個別メソッドに分けてそれらがコールされるようになっています。
それ以外は特に変わり映えしません。

###### `_stream_set_read_buffer`

- function: [`stream_set_read_buffer(resource $stream, int $size): int`](https://www.php.net/manual/function.stream-set-read-buffer.php)
- StreamWrapper: [`stream_set_option(int $option, int $arg1, int $arg2): bool`](https://www.php.net/manual/streamwrapper.stream-set-option.php)
- ThisPackage: `_stream_set_read_buffer(object $resource, int $size): bool`

これも touch などと同様、引数分岐なので個別メソッドに分けてそれらがコールされるようになっています。
それ以外は特に変わり映えしません。
敢えて言うならばドキュメントは ReadBuffer に一切の言及がないのですが、きちんと呼ばれるようです（おそらくドキュメントの誤り？）。

###### `_stream_set_write_buffer`

- function: [`stream_set_write_buffer(resource $stream, int $size): int`](https://www.php.net/manual/function.stream-set-write-buffer.php)
- StreamWrapper: [`stream_set_option(int $option, int $arg1, int $arg2): bool`](https://www.php.net/manual/streamwrapper.stream-set-option.php)
- ThisPackage: `_stream_set_write_buffer(object $resource, int $size): bool`

これも touch などと同様、引数分岐なので個別メソッドに分けてそれらがコールされるようになっています。
それ以外は特に変わり映えしません。

###### `_stream_set_timeout`

- function: [`stream_set_timeout(resource $stream, int $seconds, int $microseconds = 0): bool`](https://www.php.net/manual/function.stream-set-timeout.php)
- StreamWrapper: [`stream_set_option(int $option, int $arg1, int $arg2): bool`](https://www.php.net/manual/streamwrapper.stream-set-option.php)
- ThisPackage: `_stream_set_timeout(object $resource, float $timeout): bool`

これも touch などと同様、引数分岐なので個別メソッドに分けてそれらがコールされるようになっています。
関数版ではタイムアウト指定は $seconds+$microseconds ですが、分かりにくいので float にしてまとめています（完全に個人の好みです）。
つまり 2.5 秒に設定したい場合は `(2, 500000)` ではなく `(2.5)` と指定します。
これは元の syscall が剥き出しになっているだけだと思いますが、そこまでの精度は大抵の場合必要ないでしょう。

なお、このメソッドを実装する必要はほとんどありません。
タイムアウトを設定し、その値でもってタイムアウトを実装することは可能ですが、それを呼び元に伝える術（stream_get_meta_data の返り値）が提供されていないためです。
stream_read に &$timedout のようなレシーバ引数があれば実現できるのですが…。
ちなみに組み込みラッパーである http でもタイムアウトは実装されていないようです（http のタイムアウトはコンテキストオプションで指定します）。

###### `_stream_select`

- function: [`stream_select(?array &$read, ?array &$write, ?array &$except, ?int $seconds, ?int $microseconds = null): int|false`](https://www.php.net/manual/function.stream-select.php)
- StreamWrapper: [`stream_cast(int $cast_as): resource`](https://www.php.net/manual/streamwrapper.stream-cast.php)
- ThisPackage: `_stream_select(object $resource, int $cast_as): resource`

関数版 `stream_select` 関数はそこそこ良く使うのですが、ストリーム版 `stream_cast` でどうマッピングすればいいのか情報が足りず分からないため、この関数は特別扱いで「未実装例外」は投げずに `return false` でデフォルト実装されています。
と、いうのも、使わなければ実装しなければいいだけの話なんですが、どうも `mime_content_type` を呼ぶと `stream_select(cast)` がコールされるようです。
実際は false 返しで動作はするようなのでそのようにしています。

###### 対応表

下記が対応表です。
幅の関係上、型やデフォルト値は省いてあります。

| function                                                          | StreamWrapper                                      | ThisPackage                                                             |
|-------------------------------------------------------------------|----------------------------------------------------|-------------------------------------------------------------------------|
| mkdir($directory, $permissions, $recursive, $context)             | mkdir($path, $mode, $options)                      | _mkdir($url, $permissions, $recursive, $contextOptions, $contextParams) |
| rmdir($directory, $context)                                       | rmdir($path, $options)                             | _rmdir($url, $contextOptions, $contextParams)                           |
| touch($filename, $mtime, $atime)                                  | stream_metadata($path, $option, $value)            | _touch($url, $mtime, $atime)                                            |
| chmod($filename, $permissions)                                    | stream_metadata($path, $option, $value)            | _chmod($url, $permissions)                                              |
| chown($filename, $user)                                           | stream_metadata($path, $option, $value)            | _chown($url, $uid)                                                      |
| chgrp($filename, $group)                                          | stream_metadata($path, $option, $value)            | _chgrp($url, $gid)                                                      |
| unlink($filename, $context)                                       | unlink($path)                                      | _unlink($url, $contextOptions, $contextParams)                          |
| rename($from, $to, $context)                                      | rename($path_from, $path_to)                       | _rename($src_url, $dst_url, $contextOptions, $contextParams)            |
| stat($filename)                                                   | url_stat($path, $flags)                            | _stat($url)                                                             |
| lstat($filename)                                                  | url_stat($path, $flags)                            | _lstat(string $url)                                                     |
| opendir($directory, $context)                                     | dir_opendir($path, $options)                       | _opendir($url, $contextOptions, $contextParams)                         |
| readdir($dir_handle)                                              | dir_readdir()                                      | _readdir($resource)                                                     |
| rewinddir($dir_handle)                                            | dir_rewinddir()                                    | _rewinddir($resource)                                                   |
| closedir($dir_handle)                                             | dir_closedir()                                     | _closedir($resource)                                                    |
| fopen($filename, $mode, $use_include_path, $context)              | stream_open($path, $mode, $options, &$opened_path) | _fopen($url, $mode, $contextOptions, $contextParams)                    |
| fread($stream, $length)                                           | stream_read($count)                                | _fread($resource, $length)                                              |
| fwrite($stream, $data, $length)                                   | stream_write($data)                                | _fwrite($resource, string $data)                                        |
| ftruncate($stream, $size)                                         | stream_truncate($new_size)                         | _ftruncate($resource, $size)                                            |
| fclose($stream)                                                   | stream_close()                                     | _fclose($resource)                                                      |
| ftell($stream)                                                    | stream_tell()                                      | _ftell($resource)                                                       |
| fseek($stream, $offset, $whence)                                  | stream_seek($offset, $whence)                      | _fseek($resource, int $offset, $whence)                                 |
| feof($stream)                                                     | stream_eof()                                       | _feof($resource)                                                        |
| fflush($stream)                                                   | stream_flush()                                     | _fflush($resource)                                                      |
| flock($stream, $operation, &$would_block)                         | stream_lock($operation)                            | _flock($resource, $operation)                                           |
| fstat($stream)                                                    | stream_stat()                                      | _fstat($resource)                                                       |
| stream_set_blocking($stream, $enable)                             | stream_set_option($option, $arg1, $arg2)           | _stream_set_blocking($resource, $enable)                                |
| stream_set_read_buffer($stream, $size)                            | stream_set_option($option, $arg1, $arg2)           | _stream_set_read_buffer($resource, $size)                               |
| stream_set_write_buffer($stream, $size)                           | stream_set_option($option, $arg1, $arg2)           | _stream_set_write_buffer($resource, $size)                              |
| stream_set_timeout($stream, $seconds, $microseconds)              | stream_set_option($option, $arg1, $arg2)           | _stream_set_timeout($resource, float $timeout)                          |
| stream_select(&$read, &$write, &$except, $seconds, $microseconds) | stream_cast($cast_as)                              | _stream_select($resource, $cast_as)                                     |

#### Mixin

実際のところストリームラッパーは単体を実装すればいいというわけではなく、複数のメソッドが相互に関連します。
例えば `file_get_contents` を呼ぶと `fopen` `fread` `feof` など様々なメソッドがコールされます。
意外なところでは `include/require` を呼ぶと `stream_set_read_buffer` もコールされます。
あとは上記で挙げた `mime_content_type` での `stream_cast` も意外性が高いです。
これらをすべてをいちいち実装してはいられないし、大抵のラッパーでは実装がほぼ同じになるので、それをあらかじめ定義した trait 群です。

##### StreamTrait

ストリーム（リソース）を読み書きする用の trait です。
内部的にバッファリングします。バッファリングしないストリームラッパーというものは考えにくく、大抵のラッパーではバッファリングが必要になるはずです。
その時、これさえ use しておけばすべてのストリーム操作は完備されます。
所詮 trait なので対応できない場合は個別でメソッドを定義すればそちらが使われます。

なお、一般に想起される「バッファー」とは少し毛色が異なります。
端的に言えば「open 時にまるっと全部読んできて flush 時にまるっと全部書き込む」バッファーです。
IO を本当の意味で「バッファリング」して部分的に書き換えるのは実装難度の割に有用性が低く、大抵のユースケースでは不要のためそのような実装になっています。

##### DirectoryIOTrait

ディレクトリをサポートするスキーム用の trait です。
mkdir/rmdir の2種類（敢えて言うなら rename/stat も）しか対応する関数が存在しないですが、mkdir は再帰的処理、rmdir は中身がある場合に消せない、という共通処理があるので trait で切り出しています。
特に特筆することはありません。素直な実装になっています。

##### DirectoryIteratorTrait

scandir 用の trait です。
scandir のためには opendir,readdir,rewinddir,closedir という4つのメソッドを実装する必要がありますが、IteratorAggregate や Generator, iterable などが用意された現代でこの4つを実装する必要はありません。
Iterator を1つ与えてあげればすべての実装は完備されるはずです。そのための trait です。
scandir だけなら問題ありませんが、直に rewinddir を呼ぶ場合は rewindable な Iterator を渡す必要があります。

なお、 DirectoryIOTrait とは相関しません。
ディレクトリという概念が存在せずとも「"/" を目印にディレクトリのように探索」は実装可能です（S3 がいい例でしょう）。

##### UrlIOTrait

ストリームではなく、パスベースでの関数用の入出力（filesize, rename, unlink） trait です。
特に特筆することはありません。素直な実装になっています。

##### UrlPermissionTrait

ストリームではなく、パスベースでの関数用のパーミッション（chmod, chown, chgrp） trait です。
権限制御は行われません。誰でもいつでも変更することができます。
理由は下記の実装がめんどくさかっただけです。

- chmod: たいていのシステムでは、ファイルの所有者のみがそのモードを変更可能です
- chown: スーパーユーザーのみがファイルの所有者を変更できます
- chgrp: スーパーユーザーのみがファイルのグループを任意に変更できます。その他のユーザーは、ファイルのグループをそのユーザーがメンバーとして属しているグループに変更できます

##### 暗黙の共通 API

すべての trait に共通して暗黙の API のようなものがあります。コード上は abstract で表現されています。
例えば書き込み処理は UrlIOTrait でも StreamTrait でも使用されますし、存在確認などはほぼすべての trait で使用されます。
それらを各々実装するのは面倒なので、意図的に命名を揃えることで一部の trait で実装したメソッドを他の関数でも流用できるようにしています。
現在のところ下記のメソッドがあります。

###### function parent(): ?string;
###### function children(...): iterable;
###### function move(...): void;

URL 操作系メソッドです。
ファイル・ディレクトリを問わずコールされる可能性があります。

parent, children はそれぞれ `親URL(string)` `子URL(iterable)` を返す必要があります。

###### function getMetadata(...): ?array;
###### function setMetadata(...): void;

メタデータ操作系メソッドです。
ファイル・ディレクトリを問わずコールされる可能性があります。

getMetadata は存在チェックも兼ねます。
エントリが存在しない場合は null を返さなければなりません。

###### function createDirectory(...): void;
###### function deleteDirectory(...): void;

ディレクトリ操作系メソッドです。
前述の通り、ディレクトリをサポートするにしても最低限 mkdir と rmdir を実装すれば事足ります。
ただし mkdir には再帰オプションがあり、rmdir には空チェックの必要があります。
それらを汎化するためにこれらのメソッドが必要になります。
標準関数と合わせる必要がない、あるいは常に再帰的動作をするならこれらのメソッド（trait）を使用する必要はありません。
場合によってはそっちの方が利便性が高いでしょう。

###### function selectFile(...): string;
###### function createFile(...): void;
###### function appendFile(...): void;
###### function deleteFile(...): void;

ファイル操作系メソッドです。

selectFile は $metadata が格納される参照引数があります。
getMetadata と同じ配列が格納される必要があります。
わざわざ参照引数があるのは1回の操作でメタデータとコンテンツの両方を取得できる場合に勿体ないからです（selectFile+getMetadata がアトミックに行える場合は特に）。

createFile は $metadata が格納される引数があります。
setMetadata と同じ配列が格納される必要があります。
わざわざ引数があるのは1回の操作でメタデータとコンテンツの両方を更新できる場合に勿体ないからです（createFile+setMetadata がアトミックに行える場合は特に）。

appendFile は "a" モードで開いた場合の追記処理です。
まるっと引っ張ってきてまるっと保存でもいいんですが、ものによっては専用処理で末尾追加ができたりする（mysql の CONCAT, redis の APPEND 等）ので、使用しないと効率が全く違ってきます。
もっと言うと末尾追加ができない場合、そもそも "a" モードを使うべきではありません。

#### Stream

Stream にある○○Stream はあたかも本パッケージの主役であるような顔をしていますが、**すべてリファレンス実装です**。
作者が実装の多様性のために思いついたまま実装して「なんとなく使えそう」と至ったものが配置されているだけです。
そのまま使えないこともないですが、決め打ち実装も多く、凝ったことは全くできません。
繰り返しになりますが、**本パッケージの主役はトップディレクトリの interface+trait です** 。

ただ一応思想のようなものはあるので紹介しておきます。

```
scheme://hostname:port/path/to/?query#file.json
───   ──────  ──── ──  ────
  │          │           │    │      └─  プロトコルにおける「キー名」です
  │          │           │    └─────  プロトコルの「パラメータ」です
  │          │           └────────  プロトコルにおける「内部的な位置」を示します
  │          └─────────────── プロトコルにおける「ネットワーク上の位置」を示します
  └───────────────────── プロトコル名です
```

例えば mysql だとすると

- ネットワーク上の位置: DSN に相当します
- 内部的な位置: スキーマ・テーブル名・主キーに相当します
- パラメータ: charset などに相当します
- キー名: （対応しているなら）主キーに相当します

例としては `mysql://127.0.0.1:3306/dbname/tablename?charset=utf8mb4#pkval` となります。こう記述すると分かりやすいでしょう。
他に redis であれば `redis://127.0.0.1:6379/dbindex/key` となり、 S3 であれば `s3://endpoint/bucket/objectname` となります。
組み込みの zip スキームは fragment で内部ファイルを表すため `zip:///path/to/file.zip#localname.txt` のようになります。

このように「URL で KVS 的な規約を設け、あらゆるプロトコルでストリームラッパーを KVS 的に使えるようにする」が目的として**ありました。**
ただし実際のところは上記の通り実装の多様性のためのリファレンス実装です。

そもそも mysql であれば PDO や Doctrine があるのでストリームラッパーを使用する必要性は皆無ですし、 S3 は AWS 謹製のストリームラッパー実装があります。
下記のような狙いで実装されています。

- 全部: interface+trait だけだとテストが書けず、「本当にこれでよいのか？」が不明瞭のため実際のストリームを実装する必要があった（実際、実装していく過程で多様性が生まれた）
- Array: 高速なテスト＋file スキームとの完全互換のため
- Mysql: スキーマフルのため+flockのため
- Redis: スキーマレスのため+TTL(context)のため
- S3: 疑似ディレクトリサポートのため
- Smtp: write only なストリームがあったら面白いと思ったため（ほぼ思いつき）
- Zip: 特殊なアクセス（fragment が内部ファイル名）のため

## License

MIT

## Release

バージョニングは romantic versioning に準拠します（semantic versioning ではありません）。

- メジャー: 大規模な互換性破壊の際にアップします（アーキテクチャ、クラス構造の変更など）
- マイナー: 小規模な互換性破壊の際にアップします（引数の変更、タイプヒントの追加など）
- パッチ: 互換性破壊はありません（デフォルト引数の追加や、新たなクラスの追加、コードフォーマットなど）

### 1.1.0

- [*change] flock のシンプル化

### 1.0.0

- 公開
