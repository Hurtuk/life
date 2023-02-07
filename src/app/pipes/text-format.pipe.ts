import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'textFormat'
})
export class TextFormatPipe implements PipeTransform {

  transform(value: string, autop = true): string {
    let result = value
      .replace(/ ([\?!:;])/gi, "&nbsp;$1")
      .replace(/« /gi, "«&nbsp;")
      .replace(/ »/gi, "&nbsp;»")
      .replace(/\.\.\./gi, "…")
      .replace(/'/gi, "’")
      .replace(/(\d)((ers?)|(ères?)|(èmes?))/gi, "$1<sup>$2</sup>")
      .replace(/\r/g, "")
      .split("\n");
    if (autop) {
      result = result.map(v => !v ? '': ('<p>' + v + '</p>'));
    }
    return result.join('');
  }

}
