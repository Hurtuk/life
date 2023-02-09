import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'textFormat'
})
export class TextFormatPipe implements PipeTransform {

  transform(value: string, autop = true): string {
    let result = value
      .replace(/ ([\?!:;])/g, "&nbsp;$1")
      .replace(/« /g, "«&nbsp;<i>")
      .replace(/ »/g, "</i>&nbsp;»")
      .replace(/"([^"]+)"/g, "«&nbsp;<i>$1</i>&nbsp;»")
      .replace(/\.\.\./g, "…")
      .replace(/'/g, "’")
      .replace(/\- (.+) \-/gm, "–&nbsp;$1&nbsp;–")
      .replace(/(\d)((ers?)|(ères?)|(èmes?))/gi, "$1<sup>$2</sup>")
      .replace(/\r/g, "")
      .split("\n");
    if (autop) {
      result = result.map(v => {
        if (!v) return '';
        if (v.match(/^.*[^….!?\]]$/g)) return '<h2>' + v + '</h2>';
        return '<p>' + v + '</p>';
      });
    }
    return result.join('');
  }

}
